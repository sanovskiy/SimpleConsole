<?php
class SimpleConsole_Colors {
	const RESET = "\033[0m";

	const RED = "\033[0;31m";
	const GREEN = "\033[0;32m";
	const BLUE = "\033[0;34m";
	
	const LIGHT_RED = "\033[1;31m";
	const LIGHT_GREEN = "\033[1;32m";
	const LIGHT_BLUE = "\033[1;34m";

	const WHITE = "\033[1;37m";
	const GRAY = "\033[1;30m";

	const BROWN = "\033[0;33m";
	const PURPLE = "\033[0;35m";
	const CYAN = "\033[0;36m";
	const YELLOW = "\033[1;33m";


	const LIGHT_GRAY = "\033[0;37m";
	const LIGHT_CYAN = "\033[1;36m";
	const LIGHT_PURPLE = "\033[1;35m";
	
	public static function colorize($string,$color=null){
		if ($color != null) {
			return $color.$string.self::RESET;
		} else {
			return $string;
		}
	}
	
	public static function stripColors($string){
		return preg_replace('/\\033\[[\d\;]+m/', '', $string);
	}
}