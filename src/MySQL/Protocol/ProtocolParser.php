<?php

namespace Dazzle\MySQL\Protocol;

use BinPHP\BinSupport;
use Dazzle\Event\BaseEventEmitter;
use Dazzle\MySQL\Protocol\Command;
use Dazzle\MySQL\Protocol\CommandInterface;
use Dazzle\MySQL\Support\Queue\Queue;
use Dazzle\MySQL\Support\Queue\QueueInterface;
use Dazzle\Socket\SocketInterface;
use Dazzle\Stream\StreamInterface;
use Exception;
use SplQueue;

class ProtocolParser extends BaseEventEmitter
{
    /**
     * @var int
     */
    const PHASE_INIT       = 1;

    /**
     * @var int
     */
    const PHASE_AUTH_SENT  = 2;

    /**
     * @var int
     */
    const PHASE_AUTH_ERR   = 3;

    /**
     * @var int
     */
    const PHASE_HANDSHAKED = 4;

    /**
     * @var int
     */
    const RS_STATE_HEADER  = 0;

    /**
     * @var int
     */
    const RS_STATE_FIELD   = 1;

    /**
     * @var int
     */
    const RS_STATE_ROW     = 2;

    /**
     * @var int
     */
    const STATE_STANDBY = 0;

    /**
     * @var int
     */
    const STATE_BODY    = 1;

    /**
     * @var string
     */
    protected $user     = 'root';

    /**
     * @var string
     */
    protected $pass     = '';

    /**
     * @var string
     */
    protected $dbname   = '';

    /**
     * @var CommandInterface
     */
    protected $currCommand;

    protected $debug = false;

    protected $state = 0;

    protected $phase = 0;

    public $seq = 0;
    public $clientFlags = 239237;

    public $warnCount;
    public $message;

    protected $maxPacketSize = 0x1000000;

    protected $charsetNumber = 0x21;

    protected $serverVersion;
    protected $threadId;
    protected $scramble;

    protected $serverCaps;
    protected $serverLang;
    protected $serverStatus;

    protected $rsState = 0;
    protected $pctSize = 0;
    protected $resultRows = [];
    protected $resultFields = [];

    protected $insertId;
    protected $affectedRows;

    public $protocalVersion = 0;

    protected $errno = 0;
    protected $errmsg = '';

    protected $buffer = '';
    protected $bufferPos = 0;

    protected $connectOptions;

    /**
     * @var StreamInterface
     */
    protected $stream;

    /**
     * @var QueueInterface
     */
    protected $executor;

    /**
     * @var SplQueue
     */
    protected $queue;

    /**
     * @param SocketInterface $stream
     * @param QueueInterface $executor
     * @param mixed[] $config
     */
    public function __construct(SocketInterface $stream, QueueInterface $executor, $config = [])
    {
        $this->stream   = $stream;
        $this->executor = $executor;
        $this->queue    = new SplQueue($this);
        $this->configure($config);
        $executor->on('new', [ $this, 'handleNewCommand' ]);
    }

    public function start()
    {
        $this->stream->on('data', [ $this, 'handleData' ]);
        $this->stream->on('close', [ $this, 'handleClose' ]);
    }

    public function handleNewCommand()
    {
        if ($this->queue->isEmpty())
        {
            $this->nextRequest();
        }
    }

    public function debug($message)
    {
        if ($this->debug)
        {
            $bt = debug_backtrace();
            $caller = array_shift($bt);
            printf("[DEBUG] <%s:%d> %s\n", $caller['class'], $caller['line'], $message);
        }
    }

    public function handleData($stream, $data)
    {
        $this->append($data);
packet:
        if ($this->state === self::STATE_STANDBY)
        {
            if ($this->length() < 4)
            {
                return;
            }

            $this->pctSize = BinSupport::bytes2int($this->read(3), true);
            $this->state = self::STATE_BODY;
            $this->seq = ord($this->read(1)) + 1;
        }

        $len = $this->length();

        if ($len < $this->pctSize)
        {
            $this->debug('Buffer not enouth, return');
            return;
        }

        $this->state = self::STATE_STANDBY;

        if ($this->phase === 0)
        {
            $this->phase = self::PHASE_INIT;
            $this->protocalVersion = ord($this->read(1));
            $this->debug(sprintf("Protocol Version: %d", $this->protocalVersion));
            if ($this->protocalVersion === 0xFF)
            {
                $fieldCount = $this->protocalVersion;
                $this->protocalVersion = 0;
                printf("Error:\n");

                $this->rsState = self::RS_STATE_HEADER;
                $this->resultFields = [];
                $this->resultRows = [];
                if ($this->phase === self::PHASE_AUTH_SENT || $this->phase === self::PHASE_INIT)
                {
                    $this->phase = self::PHASE_AUTH_ERR;
                }

                goto field;
            }
            if (($p = $this->search("\x00")) === false)
            {
                return;
            }

            $options = &$this->connectOptions;

            $options['serverVersion'] = $this->read($p, 1);
            $options['threadId']      = BinSupport::bytes2int($this->read(4), true);
            $this->scramble           = $this->read(8, 1);
            $options['ServerCaps']    = BinSupport::bytes2int($this->read(2), true);
            $options['serverLang']    = ord($this->read(1));
            $options['serverStatus']  = BinSupport::bytes2int($this->read(2, 13), true);
            $restScramble             = $this->read(12, 1);
            $this->scramble          .= $restScramble;

            $this->nextRequest(true);
        }
        else
        {
            $fieldCount = ord($this->read(1));
field:
            if ($fieldCount === 0xFF)
            {
                //error packet
                $u             = unpack('v', $this->read(2));
                $this->errno   = $u[1];
                $state = $this->read(6);
                $this->errmsg  = $this->read($this->pctSize - $len + $this->length());
                $this->debug(sprintf("Error Packet:%d %s\n", $this->errno, $this->errmsg));

                $this->nextRequest();
                $this->onError();
            }
            else if ($fieldCount === 0x00)
            {
                $this->debug('Ok Packet');

                $isAuthenticated = false;
                if ($this->phase === self::PHASE_AUTH_SENT)
                {
                    $this->phase = self::PHASE_HANDSHAKED;
                    $isAuthenticated = true;
                }

                $this->affectedRows = $this->parseEncodedBinSupport();
                $this->insertId     = $this->parseEncodedBinSupport();

                $u                  = unpack('v', $this->read(2));
                $this->serverStatus = $u[1];

                $u                  = unpack('v', $this->read(2));
                $this->warnCount    = $u[1];

                $this->message      = $this->read($this->pctSize - $len + $this->length());

                if ($isAuthenticated)
                {
                    $this->onAuthenticated();
                }
                else
                {
                    $this->onSuccess();
                }

                $this->debug(sprintf("AffectedRows: %d, InsertId: %d, WarnCount:%d", $this->affectedRows, $this->insertId, $this->warnCount));
                $this->nextRequest();

            }
            // EOF
            else if ($fieldCount === 0xFE)
            {
                $this->debug('EOF Packet');
                if ($this->rsState === self::RS_STATE_ROW)
                {
                    $this->debug('result done');

                    $this->nextRequest();
                    $this->onResultDone();
                }
                else
                {
                    ++ $this->rsState;
                }
            }
            //Data packet
            else
            {
                $this->debug('Data Packet');
                $this->prepend(chr($fieldCount));

                if ($this->rsState === self::RS_STATE_HEADER)
                {
                    $this->debug('Header packet of Data packet');
                    $extra = $this->parseEncodedBinSupport();
                    //var_dump($extra);
                    $this->rsState = self::RS_STATE_FIELD;
                }
                else if ($this->rsState === self::RS_STATE_FIELD)
                {
                    $this->debug('Field packet of Data packet');
                    $field = [
                        'catalog'   => $this->parseEncodedString(),
                        'db'        => $this->parseEncodedString(),
                        'table'     => $this->parseEncodedString(),
                        'org_table' => $this->parseEncodedString(),
                        'name'      => $this->parseEncodedString(),
                        'org_name'  => $this->parseEncodedString()
                    ];

                    $this->skip(1);
                    $u                    = unpack('v', $this->read(2));
                    $field['charset']     = $u[1];

                    $u                    = unpack('v', $this->read(4));
                    $field['length']      = $u[1];

                    $field['type']        = ord($this->read(1));

                    $u                    = unpack('v', $this->read(2));
                    $field['flags']       = $u[1];
                    $field['decimals']    = ord($this->read(1));
                    //var_dump($field);
                    $this->resultFields[] = $field;

                }
                else if ($this->rsState === self::RS_STATE_ROW)
                {
                    $this->debug('Row packet of Data packet');
                    $row = [];
                    for ($i = 0, $nf = sizeof($this->resultFields); $i < $nf; ++$i)
                    {
                        $row[$this->resultFields[$i]['name']] = $this->parseEncodedString();
                    }
                    $this->resultRows[] = $row;
                    $command = $this->queue->dequeue();
                    //$command->emit('success', [ $command, [ $row ] ]);
                    $this->queue->unshift($command);
                }
            }
        }
        $this->restBuffer($this->pctSize - $len + $this->length());
        goto packet;
    }

    protected function onError()
    {
        $command = $this->queue->dequeue();
        $error = new Exception($this->errmsg, $this->errno);
        $command->setError($error);
        $command->emit('error', [ $command, $error ]);
        $this->errmsg = '';
        $this->errno  = 0;
    }

    protected function onResultDone()
    {
        $command = $this->queue->dequeue();

        $command->resultRows   = $this->resultRows;
        $command->resultFields = $this->resultFields;

        $command->emit('success', [ $command, $this->resultRows ]);

        $this->rsState      = self::RS_STATE_HEADER;
        $this->resultRows   = $this->resultFields = [];
    }

    protected function onSuccess()
    {
        $command = $this->queue->dequeue();

        $command->affectedRows = $this->affectedRows;
        $command->insertId     = $this->insertId;
        $command->warnCount    = $this->warnCount;
        $command->message      = $this->message;

        $command->emit('success', [ $command ]);
    }

    protected function onAuthenticated()
    {
        $command = $this->queue->dequeue();
        $command->emit('success', [ $command, $this->connectOptions ]);
    }

    protected function handleClose()
    {
        $this->emit('close'); // TODO ??
        if ($this->queue->count())
        {
            $command = $this->queue->dequeue();
            if ($command->equals(Command::QUIT))
            {
                $command->emit('success', [ $command ]);
            }
        }
    }

    public function append($str)
    {
        $this->buffer .= $str;
    }

    public function prepend($str)
    {
        $this->buffer = $str . substr($this->buffer, $this->bufferPos);
        $this->bufferPos = 0;
    }

    public function read($len, $skiplen = 0)
    {
        if (strlen($this->buffer) - $this->bufferPos - $len - $skiplen < 0)
        {
            throw new \LogicException('Logic Error');
        }
        $buffer = substr($this->buffer, $this->bufferPos, $len);
        $this->bufferPos += $len;
        if ($skiplen)
        {
            $this->bufferPos += $skiplen;
        }

        return $buffer;
    }

    public function skip($len)
    {
        $this->bufferPos += $len;
    }

    public function restBuffer($len)
    {
        if (strlen($this->buffer) === ($this->bufferPos+$len))
        {
            $this->buffer = '';
        }
        else
        {
            $this->buffer = substr($this->buffer,$this->bufferPos+$len);
        }
        $this->bufferPos = 0;
    }

    public function length()
    {
        return strlen($this->buffer) - $this->bufferPos;
    }

    public function search($what)
    {
        if (($p = strpos($this->buffer, $what, $this->bufferPos)) !== false)
        {
            return $p - $this->bufferPos;
        }

        return false;
    }
    /* end of buffer operation APIs */

    public function authenticate()
    {
        if ($this->phase !== self::PHASE_INIT)
        {
            return;
        }
        $this->phase = self::PHASE_AUTH_SENT;

        $clientFlags =
            Protocol::CLIENT_LONG_PASSWORD |
            Protocol::CLIENT_LONG_FLAG |
            Protocol::CLIENT_LOCAL_FILES |
            Protocol::CLIENT_PROTOCOL_41 |
            Protocol::CLIENT_INTERACTIVE |
            Protocol::CLIENT_TRANSACTIONS |
            Protocol::CLIENT_SECURE_CONNECTION |
            Protocol::CLIENT_MULTI_RESULTS |
            Protocol::CLIENT_MULTI_STATEMENTS |
            Protocol::CLIENT_CONNECT_WITH_DB;

        $packet = pack('VVc', $clientFlags, $this->maxPacketSize, $this->charsetNumber)
            . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"
            . $this->user . "\x00"
            . $this->getAuthToken($this->scramble, $this->pass)
            . ($this->dbname ? $this->dbname . "\x00" : '');

        $this->sendPacket($packet);
        $this->debug('Auth packet sent');
    }

    public function getAuthToken($scramble, $password = '')
    {
        if ($password === '')
        {
            return "\x00";
        }
        $token = sha1($scramble . sha1($hash1 = sha1($password, true), true), true) ^ $hash1;

        return $this->buildLenEncodedBinSupport($token);
    }

    /**
     * Builds length-encoded BinSupport string
     * @param string String
     * @return string Resulting BinSupport string
     */
    public function buildLenEncodedBinSupport($s)
    {
        if ($s === null)
        {
            return "\251";
        }

        $l = strlen($s);

        if ($l <= 250)
        {
            return chr($l) . $s;
        }

        if ($l <= 0xFFFF)
        {
            return "\252" . BinSupport::int2bytes(2, true) . $s;
        }

        if ($l <= 0xFFFFFF)
        {
            return "\254" . BinSupport::int2bytes(3, true) . $s;
        }

        return BinSupport::int2bytes(8, $l, true) . $s;
    }

    /**
     * Parses length-encoded BinSupport integer
     * @return integer Result
     */
    public function parseEncodedBinSupport()
    {
        $f = ord($this->read(1));
        if ($f <= 250)
        {
            return $f;
        }
        if ($f === 251)
        {
            return null;
        }
        if ($f === 255)
        {
            return false;
        }
        if ($f === 252)
        {
            return BinSupport::bytes2int($this->read(2), true);
        }
        if ($f === 253)
        {
            return BinSupport::bytes2int($this->read(3), true);
        }

        return BinSupport::bytes2int($this->read(8), true);
    }

    /**
     * Parse length-encoded string
     * @return integer Result
     */
    public function parseEncodedString()
    {
        $l = $this->parseEncodedBinSupport();
        if (($l === null) || ($l === false))
        {
            return $l;
        }

        return $this->read($l);
    }

    /**
     * Send packet to the server.
     *
     * @param string $packet
     * @return bool
     */
    protected function sendPacket($packet)
    {
        return $this->stream->write(BinSupport::int2bytes(3, strlen($packet), true) . chr($this->seq++) . $packet);
    }

    /**
     * Parse next request.
     *
     * @param bool $isHandshake
     * @return bool
     */
    protected function nextRequest($isHandshake = false)
    {
        if (!$isHandshake && $this->phase != self::PHASE_HANDSHAKED)
        {
            return false;
        }
        if (!$this->executor->isEmpty())
        {
            $command = $this->executor->dequeue();
            $this->queue->enqueue($command);

            if ($command->equals(Command::INIT_AUTHENTICATE))
            {
                $this->authenticate();
            }
            else
            {
                $this->seq = 0;
                $this->sendPacket(chr($command->getID()) . $command->getSQL());
            }
        }

        return true;
    }

    /**
     * Configure protocol parser.
     *
     * @param mixed[] $options
     */
    protected function configure($options)
    {
        foreach ($options as $option => $value)
        {
            if (property_exists($this, $option))
            {
                $this->$option = $value;
            }
        }
    }
}
