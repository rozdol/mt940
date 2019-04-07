<?php
namespace Rozdol\Mt940;

use Rozdol\Dates\Dates;
use Rozdol\Utils\Utils;
use Rozdol\Html\Html;

class Mt940
{
    private static $hInstance;

    public static function getInstance()
    {
        if (!self::$hInstance) {
            self::$hInstance = new Mt940();
        }
        return self::$hInstance;
    }

    public function __construct()
    {
            $this->dates = new Dates();
            $this->utils = new Utils();
            $this->html = new Html();
    }

    public function parse($text)
    {
        $statements = array();
        foreach ($this->splitStatements($text) as $chunk) {
            if ($statement = $this->statement($chunk)) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    public function getLine($id, $text, $offset = 0, &$position = null, &$length = null)
    {
        $pcre = '/(?:^|\r?\n)\:(' . $id . ')\:'   // ":<id>:" at the start of a line
              . '(.+)'                           // Contents of the line
              . '(:?$|\r?\n\:[[:alnum:]]{2,3}\:)' // End of the text or next ":<id>:"
              . '/Us';                           // Ungreedy matching

        // Offset manually, so the start of the offset can match ^
        if (preg_match($pcre, substr($text, $offset), $match, PREG_OFFSET_CAPTURE)) {
            $position = $offset + $match[1][1] - 1;
            $length = strlen($match[2][0]);
            return rtrim($match[2][0]);
        }

        return '';
    }
    public function splitStatements($text)
    {
        $chunks = preg_split('/^:20:/m', $text, -1);
        $chunks = array_filter(array_map('trim', array_slice($chunks, 1)));

        // Re-add the :20: at the beginning
        return array_map(function ($statement) {
            return ':20:' . $statement;
        }, $chunks);
    }
    public function splitTransactions($text)
    {
        $offset = 0;
        $length = 0;
        $position = 0;
        $transactions = array();

        while ($line = $this->getLine('61', $text, $offset, $offset, $length)) {
            $offset += 4 + $length + 2;
            $transaction = array($line);

            // See if the next description line belongs to this transaction line.
            // The description line should immediately follow the transaction line.
            $description = array();
            while ($line = $this->getLine('86', $text, $offset, $position, $length)) {
                if ($position == $offset) {
                    $offset += 4 + $length + 2;
                    $description[] = $line;
                } else {
                    break;
                }
            }

            if ($description) {
                $transaction[] = implode("\r\n", $description);
            }

            $transactions[] = $transaction;
        }

        return $transactions;
    }
    public function statement($text)
    {
        $text = trim($text);
        if (($pos = strpos($text, ':20:')) === false) {
            throw new \RuntimeException('Not an MT940 statement');
        }

        //statementHeader(substr($text, 0, $pos));
        return $this->statementBody(substr($text, $pos));
    }


    public function statementBody($text)
    {
        $statement[statementNumber]=$this->statementNumber($text);
        $statement[accountNumber]=$this->accountNumber($text);
        $statement[accountCurrency]=$this->accountCurrency($text);
        $statement[openingBalance]=$this->balance($this->openingBalance($text))[amount];
        $statement[openingBalanceDate]=$this->balance($this->openingBalance($text))[date];
        $statement[closingBalance]=$this->balance($this->closingBalance($text))[amount];
        $statement[closingBalanceDate]=$this->balance($this->closingBalance($text))[date];
        $statement[messageDate]=$this->messageDate($text)[date];

        if($statement[accountCurrency]=='')$statement[accountCurrency]=$this->balance($this->openingBalance($text))[currency];
        foreach ($this->splitTransactions($text) as $chunk) {
            $statement[transactions][]=$this->transaction($chunk);
        }

        return $statement;
    }

    public function accountNumber($text)
    {
        if ($account = $this->getLine('25', $text)) {
            return ltrim($account, '0');
        }

        return null;
    }
    public function statementNumber($text)
    {
        if ($number = $this->getLine('28|28C', $text)) {
            return $number;
        }

        return null;
    }

    public function accountCurrency($text){
        if ($number = $this->getLine('34F', $text)) {
            return substr($number,0,3);
        }
        return null;
    }

    public function openingBalance($text)
    {
        if ($line = $this->getLine('60F|60M', $text)) {
            return $line;
        }
    }

    public function closingBalance($text)
    {
        if ($line = $this->getLine('62F|62M', $text)) {
            return $line;
        }
    }

    public function messageDate($text)
    {
        if ($line = $this->getLine('13D', $text)) {
            if (!preg_match('/(\d{6})(\d{4})(\+|-)(\d{4})/', $line, $match)) {
                //throw new \RuntimeException(sprintf('Cannot parse date: "%s"', $text));
                $result[date]='';
                $result[time]='';
                $result[zone]='';

                return $result;
            }
            //return $match;

            $date = \DateTime::createFromFormat('ymd', $match[1]);
            $date->setTime(0, 0, 0);

            $result[zone]='';
            $result[time]='';
            $result[date]=$date->format('d.m.Y');

            return $result;
        }
    }

    public function transaction(array $lines)
    {
        // if (!preg_match('/(\d{6})((\d{2})(\d{2}))?(C|D)([A-Z]?)([0-9,]{1,15})/', $lines[0], $match)) {
        //     throw new \RuntimeException(sprintf('Could not parse transaction line "%s"', $lines[0]));
        // }
        if (!preg_match('/(\d{6})((\d{2})(\d{2}))?(C|D|EC|ED|RC|RD)([A-Z]?)([0-9,]{1,15})/', $lines[0], $match)) {
            throw new \RuntimeException(sprintf('Could not parse transaction line "%s"', $lines[0]));
        }


        // Parse the amount
        $amount = (float) str_replace(',', '.', $match[7]);
        if ($match[5] === 'D' || $match[5] === 'ED' || $match[5] === 'RD') {
            $amount *= -1;
        }

        // Parse dates
        $valueDate = \DateTime::createFromFormat('ymd', $match[1]);
        $valueDate->setTime(0, 0, 0);

        $bookDate = null;

        if ($match[2]) {
            // Construct book date from the month and day provided by adding the year of the value date as best guess.
            $month = intval($match[3]);
            $day = intval($match[4]);
            $bookDate = $this->getNearestDateTimeFromDayAndMonth($valueDate, $day, $month);
        }
        $parts=explode(',',$lines[0]);
        $add_info=str_replace('//', "\n", $parts[1]);
        $description = isset($lines[1]) ? $lines[1] : null;
        //$transaction = $this->reader->createTransaction();
        // foreach ($match as $key => $value) {
        //  $transaction[match][$key]=$value;
        // }
        //$transaction[lines]=$lines;
        $transaction[amount]=$amount;

        $transaction[valueDate]=$valueDate->format('d.m.Y');
        if($bookDate)$transaction[bookDate]=$bookDate->format('d.m.Y');
        $transaction[contraAccountNumber]=$this->contraAccountNumber($lines);
        $transaction[contraAccountName]=$this->contraAccountName($lines);
        $transaction[add_info]=$this->description($add_info);
        $transaction[description]=$this->description($description);
        return $transaction;
    }
    public function getNearestDateTimeFromDayAndMonth(\DateTime $target, $day, $month)
    {
        $initialGuess = new \DateTime();
        $initialGuess->setDate($target->format('Y'), $month, $day);
        $initialGuess->setTime(0, 0, 0);
        $initialGuessDiff = $target->diff($initialGuess);

        $yearEarlier = clone $initialGuess;
        $yearEarlier->modify('-1 year');
        $yearEarlierDiff = $target->diff($yearEarlier);

        if ($yearEarlierDiff->days < $initialGuessDiff->days) {
            return $yearEarlier;
        }

        $yearLater = clone $initialGuess;
        $yearLater->modify('+1 year');
        $yearLaterDiff = $target->diff($yearLater);

        if ($yearLaterDiff->days < $initialGuessDiff->days) {
            return $yearLater;
        }

        return $initialGuess;
    }

    public function contraAccountNumber(array $lines)
    {
        if (!isset($lines[1])) {
            return null;
        }

        if (preg_match('/^([0-9.]{11,14}) /', $lines[1], $match)) {
            return str_replace('.', '', $match[1]);
        }

        if (preg_match('/^GIRO([0-9 ]{9}) /', $lines[1], $match)) {
            return trim($match[1]);
        }

        return null;
    }

    public function contraAccountName(array $lines)
    {
        //echo util::var_dump($lines, TRUE,1,"lines");
        if (!isset($lines[1])) {
            return null;
        }
        $line = strstr($lines[1], "\r\n", true) ?: $lines[1];
        $parts=explode("?",$line);
        if($parts[1])return $parts[1];
        return null;
    }
    public function description($description)
    {
        return $description;
    }
    public function balance($text)
    {
        if (!preg_match('/(C|D)(\d{6})([A-Z]{3})([0-9,]{1,15})/', $text, $match)) {
            //throw new \RuntimeException(sprintf('Cannot parse balance: "%s"', $text));
            $balance[currency]='';
            $balance[amount]=0;
            $balance[date]='';

            return $balance;
        }

        $amount = (float) str_replace(',', '.', $match[4]);
        if ($match[1] === 'D') {
            $amount *= -1;
        }

        $date = \DateTime::createFromFormat('ymd', $match[2]);
        $date->setTime(0, 0, 0);

        $balance[currency]=$match[3];
        $balance[amount]=$amount;
        $balance[date]=$date->format('d.m.Y');

        return $balance;
    }

    public function test()
    {
        return "ok";
    }
}
