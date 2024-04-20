<?php

namespace FpDbTest;

use Exception;
use mysqli;


enum ParsingState {
    case parsing;
    case ignorelogic;
}

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $paramParser = new ParamParser($args);
        $query .= ' ';
        $result = "";
        $index = 0;
        $parsingState = ParsingState::parsing;

        while ($index < strlen($query) - 1) {
            if ($query[$index] == '{') {
                if ($paramParser->touchNextParam() == $this->skip()) {
                    $parsingState = ParsingState::ignorelogic;
                }
            }
            else if ($query[$index] == '}') {
                $parsingState = ParsingState::parsing;
            }
            else if ($parsingState == ParsingState::ignorelogic) {
                
            }
            else if ($query[$index] == '?') {
                $data = $paramParser->nextParam($query[$index] . $query[$index + 1]);
                $result .= $data[0];
                $index += $data[1];
            }
            else {
                $result .= $query[$index];
            }
            $index++;
        }

        echo $result . "\n\r";
        return $result;
    }

    public function skip()
    {
        return null;
    }
}

class ParamParser 
{
    private $currentIndex = 0;
    private static $paramTypes = [
        "?d" => "getNumber",
        "?f" => "getFloat", 
        "?a" => "getArray", 
        "?#" => "getIdentifier"
    ];

    public function __construct(private array $args = []) {
    }

    public function touchNextParam() {
        return $this->args[$this->currentIndex];
    }

    public function nextParam($paramType) {
        $func = "getString";
        $inc = 0;
        if (array_key_exists($paramType, self::$paramTypes)) {
            $func = self::$paramTypes[$paramType];
            $inc = 1;
        }
        return [$this->$func($this->args[$this->currentIndex++]), $inc];
    }

    private function getString($v) {
        // для защиты от SQL Inj использую просто str_replace, но лучше конечно mysql escape
        return "'" . str_replace("'", "\'", $v) . "'";
    }

    private function getNumber($v) {
        return str_replace("'", "\'", $v);
    }

    private function getFloat($v) {
        return str_replace("'", "\'", $v);
    }

    private function getArray($v) {
        $result = "";
        foreach ($v as $key => $value) {
            if ($result != "") {
                $result .= ', ';
            }

            if (is_numeric($key)) {
                $result .= $this->getNumber($value);
            }
            else {
                $result .= "`$key` = " . $this->getValue($value);
            }
        }
        return $result;
    }

    private function getValue($value){ 
        if ($value == null) {
            return "NULL";
        }
        return $this->getString($value);
    }

    private function getIdentifier($v) {
        if (is_array($v)) {
            $result = "";
            foreach ($v as $value) {
                if ($result != "") {
                    $result .= ', ';
                }
                $result .= "`" . $value . "`";
            }
            return $result;
        }
        return "`" . $v . "`";
    }
}