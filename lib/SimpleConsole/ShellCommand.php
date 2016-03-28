<?php
/**
 * Copyright (C) 2010 Pavel Terentyev (pavel@terentyev.info)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

final class SimpleConsole_ShellCommand
{
	/**
	 * @var SimpleConsole
	 */
	public $CC;

	private $commands = array();
	private $commandLine = null;
	public $rawResult = null;
	public $rawErrors = null;
	public $result = null;
	public $executed = FALSE;
	public $error = null;
	public $errorMessage = null;

	public function __construct()
	{
		$this->CC = SimpleConsole::getInstance();
	}

	public function reset()
	{
		$this->commands = array();
		$this->commandLine = null;
		$this->error = null;
		$this->errorMessage = null;
		$this->executed = false;
		$this->rawResult = null;
		$this->result = null;
	}

	/**
	 * @param mixed $command
	 * @param mixed $params
	 * @return Command
	 */
	public function addCommand($command,$params=null)
	{
		if (is_array($command))
		{
			foreach ($command as $num => $cmd)
			{
				if (empty($cmd)) {continue;}
				if (!isset($params[$num]))
				{
					$params[$num] = null;
				}
				$Index = $this->addCommand($cmd,$params[$num]);
			}
			return $Index;
		} else {
			if (!empty($command))
			{
				$Index = count($this->commands);
				$this->commands[$Index] = new Command($command,$this);
                
				$this->commands[$Index]->addParam($params);
				return $this->commands[$Index];
			} else {
				return false;
			}
		}
	}

	/**
	 * @param mixed $param
	 * @param int $commandID
	 * @return Command
	 */
	public function addParam($param,$commandID)
	{
		if (!isset($this->commands[$commandID]) || !($this->commands[$commandID] instanceof Command))
			{return false;}
		$this->commands[$commandID]->addParam($param);
		return $this->commands[$commandID];
	}

	public $lastCommandStatus=null;
	public function exec()
	{
		$execID = uniqid();
		try {
			$cmd = $this->buildCommandString();
			//$this->CC->putlog("Executing command ($execID) $cmd",  Console_Controller::HLOG_DEBUG);
			if (!file_exists('/tmp/ShellCommand')){
				mkdir('/tmp/ShellCommand',0777,true);
			}
			$outputFile = '/tmp/ShellCommand/'.$execID.'_STDOUT.tmp';
			$errorsFile = '/tmp/ShellCommand/'.$execID.'_STDERR.tmp';
			exec($cmd.' 2>'.$errorsFile.' >'.$outputFile,$_null,$this->lastCommandStatus);
			if (is_readable($outputFile) && is_writable($outputFile))
			{
				$this->rawResult = @file_get_contents($outputFile);
				@unlink($outputFile);
			}
			if (is_readable($errorsFile) && is_writable($errorsFile))
			{
				$this->rawErrors = @file_get_contents($errorsFile);
				@unlink($errorsFile);
			}
			$this->executed = TRUE;
		} catch (Exception $e) {
			$this->error = $e->getCode();
			$this->errorMessage = $e->getMessage();
			$this->CC->putlog("Error executing command ($execID).\n\t".$this->errorMessage,SimpleConsole::HLOG_ERROR);
			return $this->errorMessage;
		}
		$this->result = explode("\n",$this->rawResult);
		if ($this->result[count($this->result)-1]=="")
		{
			unset($this->result[count($this->result)-1]);
		}
		//$this->CC->putlog("Raw result ($execID):\n\t".$this->rawResult,Console_Controller::HLOG_DEBUG);
		return $this->rawResult;
	}

	public function buildCommandString()
	{
		$cmdArray = array();
		foreach ($this->commands as $command)
		{
			$cmdArray[] = $command->getCommandString();
		}
		$cmd = implode(" | ",$cmdArray);
		$this->commandLine = $cmd;
		return $cmd;
	}

}

final class Command
{
    /**
     *
     * @var SimpleConsole_ShellCommand
     */
    private $mainCommand;
	private $Command;
	private $params = array();

	function __construct($cmd,$wrapper)
	{
		$this->Command = escapeshellarg($cmd);
        $this->mainCommand = $wrapper;
	}

    /**
     *
     * @return SimpleConsole_ShellCommand
     */
    function getWrapper(){
        return $this->mainCommand;
    }
    
	/**
	 * @param mixed $param
	 * @return Command
	 */
	public function addParam($param=null)
	{
		if (!empty($param) && is_array($param))
		{
			foreach ($param as $prm)
			{
				if (empty($prm)){continue;}
				$this->addParam($prm);
			}
		} elseif (!empty($param)) {
			$this->params[] = escapeshellarg($param);
		}
		return $this;
	}

	public function getCommandString()
	{
		return $this->Command . " " . implode(" ",$this->params);
	}
}
