<?php
class SimpleConsole_Daemon {
	static public $stop_server = false;
	static public $hold_server = false;
	static public $childProcesses = array();

	static private $sigNames = array(
		SIGABRT => 'SIGABRT',
		SIGALRM => 'SIGALRM',
		SIGFPE => 'SIGFPE',
		SIGHUP => 'SIGHUP',
		SIGILL => 'SIGILL',
		SIGINT => 'SIGINT',
		SIGKILL => 'SIGKILL',
		SIGPIPE => 'SIGPIPE',
		SIGQUIT => 'SIGQUIT',
		SIGSEGV => 'SIGSEGV',
		SIGTERM => 'SIGTERM',
		SIGUSR1 => 'SIGUSR1',
		SIGUSR2 => 'SIGUSR2',
		SIGCHLD => 'SIGCHLD',
		SIGCONT => 'SIGCONT',
		SIGSTOP => 'SIGSTOP',
		SIGTSTP => 'SIGTSTP',
		SIGTTIN => 'SIGTTIN',
		SIGTTOU => 'SIGTTOU',
		SIGBUS => 'SIGBUS',
		SIGPOLL => 'SIGPOLL',
		SIGPROF => 'SIGPROF',
		SIGSYS => 'SIGSYS',
		SIGTRAP => 'SIGTRAP',
		SIGURG => 'SIGURG',
		SIGVTALRM => 'SIGVTALRM',
		SIGXCPU => 'SIGXCPU',
		SIGXFSZ => 'SIGXFSZ',
	);
	static function sigHandler($signo){
		$CC = SimpleConsole::getInstance();
		$CC->dropText('Caught signal '.self::$sigNames[$signo].' ('.$signo.')',null,true);
	    switch($signo) {
	        case SIGTERM: {
				$CC->dropText('Setting exit flag.');
	            self::$stop_server = true;
	            break;
	        }
	        case SIGKILL: {
				$CC->dropText('Exiting immediately.');
	            exit(0);
	        }
	        default: {
	            //все остальные сигналы
	        }
	    }
	}

	static public function isDaemonActive($pid_file) {
	    if( is_file($pid_file) ) {
	        $pid = file_get_contents($pid_file);
	        //проверяем на наличие процесса
	        if(posix_kill($pid,0)) {
	            //демон уже запущен
	            return true;
	        } else {
	            //pid-файл есть, но процесса нет
	            if(!unlink($pid_file)) {
	                //не могу уничтожить pid-файл. ошибка
	                exit(-1);
	            }
	        }
	    }
	    return false;
	}

	static function gracefullExit()
	{
		$CC = SimpleConsole::getInstance();
		$CC->dropText(getmypid().": Waiting for child processes");
		while(count(self::$childProcesses)>0)
		{
			while ($signaled_pid = pcntl_waitpid(-1, $status, WNOHANG)) {
				if ($signaled_pid == -1) {
					//детей не осталось
					self::$childProcesses = array();
					break;
				} else {
					unset(self::$childProcesses[$signaled_pid]);
				}
			}
		}

		$CC->dropText(getmypid().": Script finished");
		$CC->dropText('Peak memory usage: '.memory_get_peak_usage());
		exit();
	}

}

declare(ticks=1);
pcntl_signal(SIGTERM, "Console_Daemon::sigHandler");
//pcntl_signal(SIGKILL, "Console_Daemon::sigHandler");
pcntl_signal(SIGHUP, "Console_Daemon::sigHandler");
