<?php
/**
 * Copyright 2010-2016 Pavel Terentyev <pavel.terentyev@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */


/**
 * Console operations interface
 *
 * @version 3.0 Standalone
 */
class SimpleConsole
{
    /**
     * Console controller version
     */

    const VERSION = '4.0 Standalone';
    const INDENT_STR = "\t";
    const HLOG_ERROR = 'Error: ';
    const HLOG_NOTICE = 'Notice: ';
    const HLOG_WARNING = 'Warning: ';
    const HLOG_DEBUG = 'Debug: ';
    const HLOG_INFO = 'Info: ';
    const HLOG_NOLEVEL = '';

    const GBYTE = 1073741824;
    const MBYTE = 1048576;
    const KBYTE = 1024;

    static private $dumpSpacer = '	';

    /**
     * Logfile resource
     *
     * @var resource
     */
    private $logFile;

    /**
     * Main script full path & name
     *
     * @var string
     */
    private $referer;

    /**
     * Tabspace for lines
     * @var int
     */
    private $echoLevel = 0;

    /**
     * Script start time
     *
     * @var int
     */
    private $scriptStartTime;

    /**
     * Script end time
     *
     * @var int
     */
    private $scriptEndTime;

    /**
     * Debug flag
     *
     * @var boolean
     */
    public $debug = false;

    /**
     * Push ALL output to log?
     * @var boolean
     */
    public $text2log = false;

    /**
     * Instance of this class. Singletone.
     *
     * @var SimpleConsole
     */
    private static $instance = null;

    /**
     * No output if true
     *
     * @var boolean
     */
    public $keepSilence = false;

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->scriptStartTime = round(microtime(true), 4);
    }

    public function getStartTime()
    {
        return $this->scriptStartTime;
    }

    private $conOPTAliases = array(
        'h' => 'help'
    );
    private $conOPTDefaults = array();
    private $conOPTDescriptions = array();
    private $conOPT = array();

    private $rawElapsedTime = 0;

    private static $autoloadRegistered = false;

    /**
     * Parses $GLOBALS['argv'] for parameters and assigns them to an array.
     *
     * Supports:
     * -e
     * -e <value>
     * --long-param
     * --long-param=<value>
     * --long-param <value>
     * <value>
     *
     * @param array $noopt List of parameters without values
     */
    public function parseOpts($noopt = array())
    {
        //$args = getopt();
        $noopt = array_merge(array('help'), $noopt);
        $params = $_SERVER['argv'];
        // could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
        reset($params);
        next($params);
        $result = array();
        while (list(, $p) = each($params)) {
            if (mb_substr($p, 0, 1) == '-') {
                $pname = substr($p, 1);
                $value = true;
                if ($pname{0} == '-') {
                    // long-opt (--<param>)
                    $pname = substr($pname, 1);
                    if (strpos($p, '=') !== false) {
                        // value specified inline (--<param>=<value>)
                        list($pname, $value) = explode('=', substr($p, 2), 2);
                    }
                }
                // check if next parameter is a descriptor or a value
                $nextparm = current($params);
                //$this->dropText($pname);
                if (isset($this->conOPTAliases[$pname])) {
                    //$this->dropText($pname .' is alias for '.$this->conOPTAliases[$pname]);
                    $pname = $this->conOPTAliases[$pname];
                }
                if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') {
                    list(, $value) = each($params);
                }
                $result[$pname] = $value;
            } else {
                // param doesn't belong to any option
                $result[] = $p;
            }
        }
        $this->conOPT = $result;
        if ($this->getOPT('help')) {
            $this->showOPTUsage();
        }
    }

    public function showOPTUsage()
    {
        $paramColWidth = 16;
        $aliasesColWidth = 10;
        $textColWidth = 60;
        $this->dropLF();
        $this->dropText('Usage: ' . $_SERVER['argv'][0] . ' [ options ]');
        $this->dropLF();
        $this->dropText('Options:');
        $this->increaseOutputIndent();
        foreach ($this->conOPTDescriptions as $key => $value) {
            $param = '--' . $key;
            $this->cEcho(SimpleConsole_Colors::colorize($param, SimpleConsole_Colors::WHITE) . str_repeat(' ',
                    $paramColWidth - mb_strlen($param, 'utf-8')));
            $aliases = array();
            foreach ($this->conOPTAliases as $alias => $aKey) {
                if ($aKey == $key) {
                    $aliases[] = '-' . $alias;
                }
            }
            $aliases = implode(', ', $aliases);
            $this->cEcho($aliases . str_repeat(' ', $aliasesColWidth - mb_strlen($aliases, 'utf-8')));

            if (isset($this->conOPTDefaults[$key])) {
                $value .= ' default: ' . $this->conOPTDefaults[$key];
            }

            $string = str_replace(array("\r\n", "\n"), " ", $value);
            $wordsArray = explode(" ", $string);
            $curLine = '';
            foreach ($wordsArray as $num => $word) {
                $word = trim($word);
                if (0 == mb_strlen($word, 'utf-8')) {
                    continue;
                }
                if (mb_strlen($curLine, 'utf-8') + mb_strlen($word, 'utf-8') + 1 > $textColWidth) {
                    $this->cEcho($curLine);
                    $this->dropLF();
                    if (0 < $num) {
                        $this->cEcho(str_repeat(' ', $paramColWidth + $aliasesColWidth));
                    }
                    $curLine = $word;
                    continue;
                } else {
                    $curLine .= ((0 == mb_strlen($curLine, 'utf-8')) ? "" : " ") . $word;
                }
            }
            $this->cEcho($curLine);
            $this->dropLF();
            $this->dropLF();
        }
        $this->decreaseOutputIndent();

        $this->shutUp();
        $this->abort();
    }

    public function setConOPTDefault($key, $value)
    {
        $this->conOPTDefaults[$key] = $value;
    }

    public function setConOPTDescription($key, $value)
    {
        $this->conOPTDescriptions[$key] = $value;
    }

    public function addConOPTAlias($key, $alias)
    {
        if (isset($this->conOPTAliases[$alias])) {
            return false;
        }
        $this->conOPTAliases[$alias] = $key;
        return true;
    }

    public function getOpts()
    {
        return array_merge($this->conOPTDefaults, $this->conOPT);
    }

    public function getOPT($name)
    {
        if (isset($this->conOPTAliases[$name])) {
            $name = $this->conOPTAliases[$name];
        }
        if (!isset($this->conOPT[$name])) {
            if (isset($this->conOPTDefaults[$name])) {
                return $this->conOPTDefaults[$name];
            }
            return null;
        }
        return $this->conOPT[$name];
    }

    /**
     * Destructor
     */
    function __destruct()
    {
        $this->echoLevel = 0;
        $this->scriptEndTime = round(microtime(true), 4);

        if (!$this->keepSilence) {
            $this->dropLF();
            $mem = memory_get_peak_usage();
            if ($mem > self::GBYTE) {
                $mem = number_format(memory_get_peak_usage() / self::GBYTE, 2, '.', '') . ' Gbytes';
            } elseif ($mem > self::MBYTE) {
                $mem = number_format(memory_get_peak_usage() / self::MBYTE, 2, '.', '') . ' Mbytes';
            } elseif ($mem > self::KBYTE) {
                $mem = number_format(memory_get_peak_usage() / self::KBYTE, 2, '.', '') . ' Kbytes';
            } else {
                $mem .= ' bytes';
            }
            $this->dropText(
                SimpleConsole_Colors::colorize('Peak memory usage: ', SimpleConsole_Colors::WHITE) . $mem
            );
            $timeString = ' none';
            if ($this->scriptStartTime < $this->scriptEndTime) {
                $timeString = $this->getTimeString($this->scriptStartTime, $this->scriptEndTime, 'yfwdhms', true);
            }
            $this->cEcho(
                SimpleConsole_Colors::colorize("Elapsed time:", SimpleConsole_Colors::WHITE) .
                ' ' .
                $timeString .
                PHP_EOL
            );
        }
        if (is_resource($this->logFile)) {
            fclose($this->logFile);
        }
    }

    /**
     * Prevents class cloning
     */
    public function __clone()
    {
        trigger_error('Can\'t clone singletone class ' . __CLASS__, E_USER_ERROR);
    }

    /**
     * Disables any output by this class
     */
    public function shutUp()
    {
        $this->keepSilence = true;
    }

    /**
     * Returns current controller instanse. On first call creates new one.
     *
     * @return SimpleConsole
     */
    public static function getInstance()
    {
        if (!self::$autoloadRegistered) {
            spl_autoload_register(['SimpleConsole', 'autoload']);
        }
        if (self::$instance == null) {
            self::$instance = new self ();
        }
        return self::$instance;
    }

    /**
     * @param $classname
     * @return bool
     */
    public static function autoload($classname)
    {
        $classpath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $classname) . '.php';
        if (!file_exists($classpath)) {
            return false;
        }
        /** @noinspection PhpIncludeInspection */
        require $classpath;
        return true;
    }

    /**
     * Returns microtime
     * @return float
     */
    function getmicrotime()
    {
        list ($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * @param int $time
     * @return float|int
     */
    public function calculateExecutionTime($time = 0)
    {
        if ($time > 0) {
            return ($time - $this->scriptStartTime);

        }
        $this->rawElapsedTime = round(($this->scriptEndTime - $this->scriptStartTime) / 1000);
        return $this->rawElapsedTime;
    }

    /**
     * @param $time1
     * @param null $time2
     * @param string $format yfwdhisS
     * @param bool $returnString
     * @return array|string
     */
    public function getTimeString($time1, $time2 = null, $format = 'yfdhisS', $returnString = false)
    {
        $time2 = ($time2 === null) ? time() : $time2;
        $timeDiff = round(abs($time2 - $time1), 3);
        if (strpos($timeDiff, '.') > -1) {
            list($timeDiff, $ms) = explode('.', $timeDiff);

        } else {
            $ms = 0;
        }
        $sign = $time2 > $time1 ? 1 : -1;
        $out = [];
        $left = $timeDiff;
        $format = array_unique(str_split(preg_replace('`[^ymwdhisS]`', '', $format)));
        $format_count = count($format);
        $a = [
            'y' => strtotime('+1 year', 0),
            'm' => strtotime('+1 month', 0),
            'w' => strtotime('+1 week', 0),
            'd' => strtotime('+1 day', 0),
            'h' => strtotime('+1 hour', 0),
            'i' => strtotime('+1 minute', 0),
            's' => 1
        ];
        $i = 0;
        foreach ($a as $k => $v) {
            if (in_array($k, $format)) {
                ++$i;
                if ($i != $format_count) {
                    $out [$k] = $sign * (int)($left / $v);
                    $left = $left % $v;
                } else {
                    $out [$k] = $sign * ($left / $v);
                }
            } else {
                $out [$k] = 0;
            }
        }
        $out['S'] = floatval(substr($ms, 0, 3) . (strlen($ms) > 3 ? '.' . substr($ms, 3) : ''));
        $strings = [];
        if (!$returnString) {
            return $out;
        }

        foreach ($out as $k => $v) {
            if ($v == 0) {
                continue;
            }
            switch ($k) {
                default :
                    break;
                case "y" :
                    $strings [] = $v . " year" . ($v > 1 ? "s" : "");
                    break;
                case "m" :
                    $strings [] = $v . " month" . ($v > 1 ? "s" : "");
                    break;
                case "w" :
                    $strings [] = $v . " week" . ($v > 1 ? "s" : "");
                    break;
                case "d" :
                    $strings [] = $v . " day" . ($v > 1 ? "s" : "");
                    break;
                case "h" :
                    $strings [] = $v . " hour" . ($v > 1 ? "s" : "");
                    break;
                case "i" :
                    $strings [] = $v . " minute" . ($v > 1 ? "s" : "");
                    break;
                case "s" :
                    $strings [] = $v . " second" . ($v > 1 ? "s" : "");
                    break;
                case "S" :
                    $strings [] = $v . " microsecond" . ($v > 1 ? "s" : "");
                    break;
            }
        }
        return implode(" ", $strings);

    }

    /**
     * Sets log file
     *
     * @param string $logFile
     * @return boolean
     */
    function setLogFile($logFile)
    {
        $dirname = pathinfo($logFile, PATHINFO_DIRNAME);
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        }
        if ($this->logFile = fopen($logFile, "a")) {
            return true;
        } else {
            $this->dropError("Can't open logfile $logFile for writing!", self::HLOG_ERROR);
            return false;
        }
    }

    /**
     * Sets "referred" script name
     * @param string $referer
     * @return SimpleConsole
     */
    function setReferer($referer)
    {
        $this->referer = $referer;
        return $this;
    }

    /**
     * returns "referred" script name
     * @return string $referer
     */
    function getReferer()
    {
        return $this->referer;
    }

    /**
     * Puts string into logfile in format "YYYY/MM/DD HH:MM:SS [TAB] REFERER [TAB] string"
     *
     * @param string $text
     * @param string $logLevel
     * @return bool
     */
    function putLog($text, $logLevel = self::HLOG_NOLEVEL)
    {
        $logType = '';
        switch ($logLevel) {
            case self::HLOG_DEBUG:
                $logType = "Debug:";
                break;
            case self::HLOG_ERROR:
                $logType = "Error:";
                break;
            case self::HLOG_INFO:
                $logType = "Info:";
                break;
            case self::HLOG_NOLEVEL:
                $logType = "";
                break;
            case self::HLOG_NOTICE:
                $logType = "Notice:";
                break;
            case self::HLOG_WARNING:
                $logType = "Warning:";
                break;
        }
        $putstring =
            date("Y/m/d H:i:s", time()) .
            ($this->appendPidOnOutput ? ' #' . getmypid() : '') . ' ' .
            $this->referer . ' ' .
            $logType . ' ' . $text .
            PHP_EOL;
        if (is_resource($this->logFile)) {
            fputs($this->logFile, $putstring);
        } else {
            $this->dropError("There is no defined logfile! Define logfile before doing any logfile actions!",
                self::HLOG_WARNING);
            return false;
        }
        return true;
    }

    /**
     * @param Exception $e
     */
    function handleException(Exception $e)
    {
        $message = "ERROR => " . $e->getMessage() . "\n\n";
        $message .= "TRACE => " . $e->getTraceAsString() . "\n\n";
        //$message .= "STREAM => " . $e->stream;

        $message .= PHP_EOL . str_repeat("=", 80);
        $this->putLog($message, self::HLOG_NOLEVEL);
        $this->abort("Exception catched.");
    }

    /**
     * Aborts script execution with putting message to logfile and tries to write exit message to console
     *
     * @param string $reason
     * @param string $error_level
     */
    function abort($reason = "no reason", $error_level = self::HLOG_NOLEVEL)
    {
        if (in_array($error_level, array(self::HLOG_ERROR, self::HLOG_DEBUG))) {
            $this->putLog("Script aborted. Reason: " . $reason, $error_level);
        }
        $this->shutUp();
        die();
    }

    function packLogfile($logfile)
    {
        $dir = dirname($logfile);
        $logname = basename($logfile);
        $gzippedFile = $dir . "/" . date("Y-m-d") . "_" . $logname . ".gz";
        $logfileText = file_get_contents($logfile);
        if (file_exists($gzippedFile)) {
            $oldLog = file_get_contents($gzippedFile);
            $oldLogText = gzdecode($oldLog);
            $logfileText = $oldLogText . $logfileText;
        }
        $gzippedText = gzencode($logfileText, 9);
        file_put_contents($gzippedFile, $gzippedText);
        unlink($logfile);
    }

    public $appendPidOnOutput = false;
    public $appendPidOnLog = false;

    /**
     * $text colorized with $colorize to console
     * then puts $text to logfile if TEXT2LOG is true and $put2log is false
     *
     * @param string|array $text
     * @param string $colorize
     * @param bool $put2log
     */
    function dropText($text, $colorize = null, $put2log = false)
    {
        if (!$this->keepSilence) {
            if (is_array($text)) {
                foreach ($text as $line) {
                    fputs(STDOUT,
                        ($this->appendPidOnOutput ? '#' . getmypid() . '	' : '') . SimpleConsole_Colors::colorize(str_repeat(self::INDENT_STR,
                                $this->echoLevel) . $line, $colorize) . "\n");
                }
            } else {
                fputs(STDOUT,
                    ($this->appendPidOnOutput ? '#' . getmypid() . '	' : '') . SimpleConsole_Colors::colorize(str_repeat(self::INDENT_STR,
                            $this->echoLevel) . $text, $colorize) . "\n");
            }
        }
        if ($this->text2log || $put2log) {
            if (is_array($text)) {
                foreach ($text as $line) {
                    $this->putLog($line);
                }
            } else {
                $this->putLog($text);
            }
        }
    }

    /**
     * If DEBUG constant is true -> writes \n to console
     * @param int $multiplier
     */
    function dropLF($multiplier = 1)
    {
        if (!$this->keepSilence) {
            $this->cEcho(str_repeat(PHP_EOL, intval($multiplier)));
        }
    }

    /**
     * writes $text colorized with $colorize to console
     *
     * @param string $text
     * @param string $colorize
     */
    function cEcho($text, $colorize = null)
    {
        if (!$this->keepSilence) {
            fwrite(STDOUT, $colorize ? SimpleConsole_Colors::colorize($text, $colorize) : $text);
        }
    }

    /**
     * Returns colorised text.
     * $color - UNIX console color
     *
     * @deprecated deprecated since version 2.0
     * @param string $text
     * @param string $color
     * @return string
     */
    function colorize_output($text, $color = null)
    {
        SimpleConsole_Colors::colorize($text, $color);
    }

    /**
     * Returns colorised text.
     * $color - UNIX console color
     *
     * @param string $string
     * @param string $color
     * @return string
     * @internal param string $text
     * @deprecated Use SimpleConsole_Colors::colorize
     */
    function colorizeOutput($string = '', $color = null)
    {
        return SimpleConsole_Colors::colorize($string, $color);
    }

    /**
     * UNSAFE cursor movement to position ($x,$y)
     *
     * @param int $x
     * @param int $y
     */
    function curPos($x, $y)
    {
        $x = intval($x);
        $y = intval($y);
        fputs(STDOUT, "\033[" . $x . ";" . $y . "H");
        // Alternate way
        //fputs(STDOUT,"\033[".$x.";".$y."f");
    }

    /**
     * Move cursor up $n lines
     *
     * @param int $n
     */
    function curUp($n = 1)
    {
        $n = intval($n);
        fputs(STDOUT, "\033[" . $n . "A");
    }

    /**
     * Moves cursor down $n lines
     *
     * @param int $n
     */
    function curDown($n = 1)
    {
        $n = intval($n);
        fputs(STDOUT, "\033[" . $n . "B");
    }

    /**
     * Moves cursor forward $n chars
     *
     * @param int $n
     */
    function curForward($n = 1)
    {
        $n = intval($n);
        fputs(STDOUT, "\033[" . $n . "C");
    }

    /**
     * Moves cursor backwards $n chars
     *
     * @param int $n
     */
    function curBackward($n = 1)
    {
        $n = intval($n);
        fputs(STDOUT, "\033[" . $n . "D");
    }

    /**
     * Clears screen and puts cursor at position (0,0)
     *
     */
    function clear()
    {
        fputs(STDOUT, "\033[2J");
        $this->curPos(0, 0);
    }

    /**
     * Erases from cursor to the end of line
     */
    function eraseToEOL()
    {
        fputs(STDOUT, "\033[K");
    }

    /**
     * Saves current position of cursor
     */
    function saveCurPos()
    {
        fputs(STDOUT, "\033[s");
    }

    /**
     * Restores saved position of cursor
     */
    function restoreCurPos()
    {
        fputs(STDOUT, "\033[u");
    }

    function inKey($vals = null)
    {
        $inKey = "";
        if (!is_null($vals)) {
            $vals = (array)$vals;
            While (!in_array($inKey, $vals)) {
                $inKey = trim(`read -s -n1 valu;echo \$valu`);
            }
        } else {
            $inKey = trim(`read -s -n1 valu;echo \$valu`);
        }
        return $inKey;
    }

    /**
     * @deprecated
     * @param array $strings
     * @param string $colorize
     * @return SimpleConsole
     */
    function draw_logo($strings, $colorize = null)
    {
        return $this->drawLogo($strings, $colorize);
    }

    /**
     * @param array $strings
     * @param string $colorize
     * @return SimpleConsole
     */
    function drawLogo($strings, $colorize = null)
    {
        $output = array();
        $strings = (array)$strings;
        $max_len = 0;
        foreach ($strings as $string) {
            if (is_array($string)) {
                $string = array_shift($string);
            }
            $max_len = strlen($string) > $max_len ? strlen($string) : $max_len;
        }
        $output[] = str_repeat("-", $max_len + 4);
        foreach ($strings as $string) {
            if (is_array($string)) {
                $color = array_pop($string);
                $string = array_shift($string);
            } else {
                $color = $colorize;
            }
            $output[] = "|" . SimpleConsole_Colors::colorize(str_pad($string, $max_len + 2, ' ', STR_PAD_BOTH),
                    $color) . "|";
        }
        $output[] = str_repeat("-", $max_len + 4);
        foreach ($output as $string) {
            $this->cEcho($string . "\n");
        }
        return true;
    }

    /**
     * Writes $error to logfile and tries to write error message to console
     *
     * @param string $error
     * @param int|string $errortype
     * @param bool $putToLog
     * @internal param bool $put2Log
     */
    function dropError($error, $errortype = self::HLOG_ERROR, $putToLog = false)
    {
        switch ($errortype) {
            default:
            case self::HLOG_ERROR:
                $error_handler = "ERROR";
                $color = SimpleConsole_Colors::LIGHT_RED;
                break;
            case self::HLOG_WARNING:
                $error_handler = "WARNING";
                $color = SimpleConsole_Colors::YELLOW;
                break;
            case self::HLOG_DEBUG:
            case self::HLOG_NOTICE:
            case self::HLOG_INFO:
            case self::HLOG_NOLEVEL:
                $error_handler = "NOTICE";
                $color = SimpleConsole_Colors::GRAY;
                break;
        }
        $this->dropText($error_handler . ": " . $error, $color, $putToLog);
        /* 		if (is_resource($this->logfile))
          {
          $this->putLog($error,$errortype);
          } */
    }

    private $tableColsLengths = array();
    private $outputTable = array();

    public function startTable()
    {
        $this->outputTable = array();
    }

    public function pushTableRow($row = null, $isHeader = false)
    {
        $this->outputTable[] = array();
        if (!is_null($row) && !empty($row)) {
            if (is_array($row)) {
                foreach ($row as $text) {
                    if (!is_array($text)) {
                        $text = array($text, null);
                    }
                    $this->pushTableCell($text, $isHeader);
                }
            }
        }
        return $this;
    }

    public function pushTableCell($cellText, $isHeader = false)
    {
        $curRow = count($this->outputTable) - 1;
        if ($curRow > 0 && count($this->outputTable[$curRow]) >= count($this->outputTable[0])) {
            $this->pushTableRow();
        }
        $curCell = count($this->outputTable[$curRow]);
        $text = '';
        $color = null;

        if (is_array($cellText)) {
            list($text, $color) = $cellText;
        }

        $maxLen = 0;
        foreach (explode("\n", $text) as $line) {
            if (strlen($line) > $maxLen) {
                $maxLen = strlen($line);
            }
        }
        if (!isset($this->tableColsLengths[$curCell]) || $this->tableColsLengths[$curCell] < $maxLen) {
            $this->tableColsLengths[$curCell] = $maxLen;
        }
        $_ = array();
        $_['header'] = $isHeader;
        $_['text'] = trim($text);
        $_['color'] = $color;
        $this->outputTable[$curRow][] = $_;
        return $this;
    }

    public function flushTable()
    {
        $prependPID = $this->appendPidOnOutput;
        $this->appendPidOnOutput = false;
        $tableLineLength = 1;
        foreach ($this->tableColsLengths as $length) {
            $tableLineLength += $length + 3;
        }
        $this->dropText(str_repeat('-', $tableLineLength));
        foreach ($this->outputTable as $row) {
            $this->cEcho('|');
            foreach ($row as $num => $cell) {
                $cellWidth = $this->tableColsLengths[$num] + 2;
                $freeSpace = $cellWidth - strlen($cell['text']);
                if ($cell['header']) {
                    if ($freeSpace % 2 == 0) {
                        $rightFreeSpace = $leftFreeSpace = $freeSpace / 2;
                    } else {
                        $leftFreeSpace = ($freeSpace - 1) / 2;
                        $rightFreeSpace = $leftFreeSpace + 1;
                    }
                    $cell['text'] = str_repeat(' ', $leftFreeSpace) . $cell['text'] . str_repeat(' ', $rightFreeSpace);
                } else {
                    $cell['text'] = ' ' . $cell['text'] . str_repeat(' ', $freeSpace - 1);
                }
                $this->cEcho($cell['text'], $cell['color']);
                $this->cEcho('|');
            }
            $this->dropLF();
            $this->dropText(str_repeat('-', $tableLineLength));
        }
        $this->tableColsLengths = $this->outputTable = array();
        $this->appendPidOnOutput = $prependPID;
        return $this;
    }

    public $ignoredClasses = array(
        __CLASS__,
        "Zend_Db_Adapter_Pdo_Mysql",
        "Zend_Db_Adapter_Mysqli",
        "Zend_Db_Adapter_Mysql"
    );
    public $ignoredProperties = array();//"DBTable", "TFields", "Properties", "field2PropertyTransform", "property2FieldTransform", "field2ReturnTransform");

    public function performDump(&$obj, $LeftSp = "")
    {
        //SimpleConsole_Colors::colorize(,self::C_)
        $ignoredClasses = $this->ignoredClasses;
        if (is_array($obj)) {
            $type = SimpleConsole_Colors::colorize("Array[",
                    SimpleConsole_Colors::BLUE) . SimpleConsole_Colors::colorize(count($obj),
                    SimpleConsole_Colors::LIGHT_BLUE) . SimpleConsole_Colors::colorize("]", SimpleConsole_Colors::BLUE);
        } elseif (is_object($obj)) {
            $className = get_class($obj);
            $extendInfo = get_parent_class($obj);
            $type = SimpleConsole_Colors::colorize("Instance of " . $className,
                    SimpleConsole_Colors::YELLOW) . ($extendInfo != "" ? SimpleConsole_Colors::colorize(" extends " . $extendInfo,
                    SimpleConsole_Colors::BROWN) : "");
            if (in_array($className, $ignoredClasses)) {
                return $type . " (dump skipped)";
            }
        } elseif (gettype($obj) == "boolean") {
            return SimpleConsole_Colors::colorize("(boolean) ",
                SimpleConsole_Colors::WHITE) . ($obj ? "true" : "false");
        } elseif (gettype($obj) == "double") {
            return SimpleConsole_Colors::colorize('(double/float)', SimpleConsole_Colors::LIGHT_PURPLE) . ' ' . ($obj);
        } elseif ($obj === null) {
            return SimpleConsole_Colors::colorize("NULL", SimpleConsole_Colors::GRAY);
        } elseif (is_int($obj)) {
            return SimpleConsole_Colors::colorize('(int)', SimpleConsole_Colors::LIGHT_CYAN) . " " . $obj;
        } elseif (is_resource($obj)) {
            $Buf = $obj . "";
            $Buf = SimpleConsole_Colors::colorize($Buf, SimpleConsole_Colors::RED);
            $resType = get_resource_type($obj);
            if ($resType == "stream") {
                $streamInfo = stream_get_meta_data($obj);
                $Buf .= " | Resource meta data => " . $this->performDump($streamInfo, $LeftSp);
            }
            return $Buf;
        } elseif (gettype($obj) == "string") {
            return SimpleConsole_Colors::colorize('(string[',
                SimpleConsole_Colors::GREEN) . SimpleConsole_Colors::colorize(strlen($obj),
                SimpleConsole_Colors::LIGHT_GREEN) . SimpleConsole_Colors::colorize("])",
                SimpleConsole_Colors::GREEN) . " \"" . trim($obj/* preg_replace("/\n/", "\\n", $obj) */) . "\"";
        } else {
            return "(" . gettype($obj) . ") " . $obj;
        }
        //$Buf = "\n";
        $Buf = $type;
        $LeftSp .= self::$dumpSpacer;
        for (Reset($obj); list ($k, $v) = each($obj);) {
            $new = "";
            $prefix = "";
            $postfix = "";
            for ($chrInd = 0; $chrInd < strlen($k); $chrInd++) {
                if (ord(substr($k, $chrInd, 1)) != 0) {
                    $new .= substr($k, $chrInd, 1);
                } elseif ($chrInd != 0) {
                    switch ($new) {
                        case $className :
                            $prefix = "+";
                            $postfix = " [PRIVATE] ";
                            break;
                        case "*" :
                            $prefix = "*";
                            $postfix = " [PROTECTED]";
                            break;
                        default :
                            $prefix = "<";
                            $postfix = " [inherited from class '$new']";
                            break;
                    }
                    $new = "";
                }
            }
            if ("$k" == "GLOBALS") {
                continue;
            }
            $k = $prefix . $new . $postfix;
            if ($new !== 0 && in_array($new, $this->ignoredProperties)) {
                //$Buf.="\n" . $LeftSp . $k . " => (Skipping dump due rule)";
                continue;
            }
            if (is_object($v) && isset($className) && get_class($v) == $className) {
                $Buf .= "\n" . $LeftSp . SimpleConsole_Colors::colorize($k,
                        SimpleConsole_Colors::LIGHT_GRAY) . " => Instance of " . get_class($v) . " (Skipping dump due to possible recursion)";
            } else {
                $Buf .= "\n" . $LeftSp . SimpleConsole_Colors::colorize($k,
                        SimpleConsole_Colors::LIGHT_GRAY) . " => " . $this->performDump($v, $LeftSp);
            }
        }
        return $Buf;
    }

    function extensionInfo($extName = null, $woFuncs = false, $woConst = false)
    {
        $ret = "";
        if (is_null($extName)) {
            $ext = get_loaded_extensions();
            foreach ($ext as $extName) {
                $ret .= $this->getExtensionDetails($extName, $woFuncs, $woConst);
            }
        } else {
            $ret .= $this->getExtensionDetails($extName, $woFuncs, $woConst);
        }
        return $ret;
    }

    function getExtensionDetails($extension, $woFuncs = false, $woConst = false)
    {
        $consts = get_defined_constants(true);
        $ret = "{$extension}\n";
        if (!$woFuncs) {
            $ret .= "    functions:\n";
            $funcs = get_extension_funcs($extension);
            if (is_array($funcs) && count($funcs) > 0) {
                foreach ($funcs as $funcName) {
                    $ret .= "        " . $funcName . "\n";
                }
            }
        }
        if (!$woConst && isset($consts [$extension])) {
            $ret .= "    constants:\n";
            foreach ($consts [$extension] as $constName => $constVal) {
                $ret .= "        " . $constName . " => " . $constVal . "\n";
            }
        }
        return $ret;
    }

    public $dumpsAdmitted = false;

    static function getDump($var)
    {
        self::getInstance()->dumpsAdmitted = true;
        $trace = debug_backtrace();
        //$index = count($trace)-2;
        $index = 1;
        if (isset($trace [$index] ["file"])) {
            $file = str_replace("\\", "/", $trace [$index] ["file"]);
        } else {
            $file = "??? (Maybe zend view.)";
        }
        if (isset($trace [$index] ["line"])) {
            $line = $trace [$index] ["line"];
        } else {
            $line = "??? (Maybe zend view.)";
        }
        //$this->putLog(__FUNCTION__ . " called from $file at line $line", self::HLOG_INFO);
        return self::getInstance()->performDump($var);
    }

    static function showDump($var)
    {
        if (!self::getInstance()->keepSilence) {
            fwrite(STDOUT, self::getDump($var) . "\n");
        }
    }

    function All2Array($obj, $makeHash = false)
    {
        if (is_object($obj)) {
            $vars = get_object_vars($obj);
            return $this->All2Array($vars, $makeHash);
        } elseif (is_array($obj)) {
            if ($makeHash) {
                $newObj = [];
            }
            foreach ($obj as $key => $val) {
                if ($makeHash) {
                    $newObj [] = $this->All2Array($val, true);
                } else {
                    $obj [$key] = $this->All2Array($val, false);
                }
            }
            if ($makeHash) {
                $obj = $newObj;
            }
            return $obj;
        } else {
            return $obj;
        }
    }

    function increaseOutputIndent()
    {
        $this->echoLevel++;
    }

    function decreaseOutputIndent()
    {
        if ($this->echoLevel == 0) {
            return false;
        }
        $this->echoLevel--;
        return true;
    }

    public function getMemoryUsage()
    {
        $_ = array();
        $_['peak'] = memory_get_peak_usage(true);
        $_['current'] = memory_get_usage(true);
        return $_;
    }

    static function getFileSize($file)
    {
        $CC = self::getInstance();
        //$cmd = '/usr/bin/ls -la '.$file;
        $shell = new SimpleConsole_ShellCommand();
        $shell
            ->addCommand('/usr/bin/ls')
            ->addParam('-la')
            ->addParam($file);
        $data = $shell->exec();
        if (preg_match('/No such file or directory/', $data)) {
            return 0;
        }

        $pattern = '/([\-rwx]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)/';
        preg_match($pattern, $data, $res);
        // 1 - access rights
        // 2 -
        // 3 - owner
        // 4 - folder
        // 5 - size in bytes
        // 6 - last modification month
        // 7 - last modification date
        // 8 - last modification year
        // 9 - file path
        if (isset($res[5])) {
            return intval($res[5]);
        }
        return false;
    }

}