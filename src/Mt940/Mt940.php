<?php
namespace Rozdol\Mt940;

use Rozdol\Dates\Dates;
use Rozdol\Utils\Utils;
use Rozdol\Html\Html;


// Fields covered
// 20
// 25
// 28
// 34
// 13
// 60
// 61
// 86
// 62
// 64
// 65
// 90

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
        $statement[statementType]=$this->statement_type($text);
        $statement[referenceNumber]=$this->referenceNumber($text);
        $statement[statementNumber]=$this->statementNumber($text);
        $statement[accountNumber]=$this->accountNumber($text);
        $statement[accountCurrency]=$this->accountCurrency($text);
        $statement[openingBalance]=$this->balance($this->openingBalance($text))[amount];
        $statement[openingBalanceDate]=$this->balance($this->openingBalance($text))[date];
        $statement[closingBalance]=$this->balance($this->closingBalance($text))[amount];
        $statement[closingBalanceDate]=$this->balance($this->closingBalance($text))[date];
        $statement[closingBalanceAvailable]=$this->balance($this->closingBalanceAvailable($text))[amount];
        $statement[closingBalanceAvailableDate]=$this->balance($this->closingBalanceAvailable($text))[date];
        $statement[forwardBalanceAvailable]=$this->balance($this->forwardBalanceAvailable($text))[amount];
        $statement[forwardBalanceAvailableDate]=$this->balance($this->forwardBalanceAvailable($text))[date];
        $statement[debitEntriesAmount]=$this->balance($this->debitEntries($text))[amount];
        $statement[creditEntriesAmount]=$this->balance($this->creditEntries($text))[amount];
        $statement[messageDate]=$this->messageDate($text)[date];
        $statement[messageTime]=$this->messageDate($text)[time];
        $statement[messageTimeZone]=$this->messageDate($text)[zone];

        if($statement[accountCurrency]=='')$statement[accountCurrency]=$this->balance($this->openingBalance($text))[currency];
        $tr_no=0;
        $transactions=[];
        $bookDates=[];
        $valueDates=[];
        foreach ($this->splitTransactions($text) as $chunk) {
            $transactions[$tr_no]=$this->transaction($chunk,$statement[openingBalanceDate],$statement[closingBalanceDate]);

            $bookDates[]=$transactions[$tr_no][bookDate];
            $valueDates[]=$transactions[$tr_no][valueDate];
            $tr_no++;
        }
        $statement[bookDateFrom]=date('d.m.Y', min(array_map('strtotime', $bookDates)));
        $statement[bookDateTo]=date('d.m.Y', max(array_map('strtotime', $bookDates)));
        $statement[valueDateFrom]=date('d.m.Y', min(array_map('strtotime', $valueDates)));
        $statement[valueDateTo]=date('d.m.Y', max(array_map('strtotime', $valueDates)));
        $statement[bookDateFrom]=($statement[bookDateFrom]!='01.01.1970')?$statement[bookDateFrom]:'';
        $statement[bookDateTo]=($statement[bookDateTo]!='01.01.1970')?$statement[bookDateTo]:'';
        $statement[valueDateFrom]=($statement[valueDateFrom]!='01.01.1970')?$statement[valueDateFrom]:'';
        $statement[valueDateTo]=($statement[valueDateTo]!='01.01.1970')?$statement[valueDateTo]:'';
        $statement[transactionsNumber]=$tr_no;
        $statement[transactions]=$transactions;

        if($statement[messageDate]=='' && $statement[closingBalanceDate]!='')$statement[messageDate]=$statement[closingBalanceDate];
        return $statement;
    }

    public function statement_type($text)
    {
        $text = trim($text);
        if (($pos = strpos($text, ':13D:')) === false) {
            return "MT940";
        }else{
            return "MT942";
        }
    }

    public function accountNumber($text)
    {
        if ($account = $this->getLine('25', $text)) {
            return ltrim($account, '0');
        }

        return null;
    }
    public function referenceNumber($text)
    {
        if ($number = $this->getLine('20', $text)) {
            return $number;
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

    public function closingBalanceAvailable($text)
    {
        if ($line = $this->getLine('64', $text)) {
            return $line;
        }
    }

    public function forwardBalanceAvailable($text)
    {
        if ($line = $this->getLine('65', $text)) {
            return $line;
        }
    }

    public function debitEntries($text)
    {
        if ($line = $this->getLine('90D', $text)) {
            return $line;
        }
    }

    public function creditEntries($text)
    {
        if ($line = $this->getLine('90C', $text)) {
            return $line;
        }
    }

    public function messageDate($text)
    {
        if ($line = $this->getLine('13D', $text)) {
            if (!preg_match('/(\d{10}[+|-]\d{4})/', $line, $match)) {
                //throw new \RuntimeException(sprintf('Cannot parse date: "%s"', $text));
                $result[date]='';
                $result[time]='';
                $result[zone]='';

                return $result;
            }
            //return $match;
            //echo $this->html->pre_display($match,"match");
            $date = \DateTime::createFromFormat('ymdHiT', $match[1]);
            //echo $this->html->pre_display($date,"date");
            //$date->setTime(0, 0, 0);

            $result[zone]=$date->format('T');;
            $result[time]=$date->format('H:i');
            $result[date]=$date->format('d.m.Y');

            return $result;
        }
    }

    public function transaction(array $lines, $openingBalanceDate='',$closingBalanceDate='')
    {
        $codes=[
            'BNK'=>'Securities Related Item – Bank fees',
            'BOE'=>'Bill of exchange',
            'BRF'=>'Brokerage fee',
            'CAR'=>'Securities Related Item – Corporate Actions Related (Should only be used when no specific corporate action event code is available)',
            'CAS'=>'Securities Related Item – Cash in Lieu',
            'CHG'=>'Charges and other expenses',
            'CHK'=>'Cheques',
            'CLR'=>'Cash letters/Cheques remittance',
            'CMI'=>'Cash management item – No detail',
            'CMN'=>'Cash management item – Notional pooling',
            'CMP'=>'Compensation claims',
            'CMS'=>'Cash management item – Sweeping',
            'CMT'=>'Cash management item -Topping',
            'CMZ'=>'Cash management item – Zero balancing',
            'COL'=>'Collections (used when entering a principal amount)',
            'COM'=>'Commission',
            'CPN'=>'Securities Related Item – Coupon payments',
            'DCR'=>'Documentary credit (used when entering a principal amount)',
            'DDT'=>'Direct Debit Item',
            'DIS'=>'Securities Related Item – Gains disbursement',
            'DIV'=>'Securities Related Item – Dividends',
            'EQA'=>'Equivalent amount',
            'EXT'=>'Securities Related Item – External transfer for own account',
            'FEX'=>'Foreign exchange',
            'INT'=>'Interest',
            'LBX'=>'Lock box',
            'LDP'=>'Loan deposit',
            'MAR'=>'Securities Related Item – Margin payments/Receipts',
            'MAT'=>'Securities Related Item – Maturity',
            'MGT'=>'Securities Related Item – Management fees',
            'MSC'=>'Miscellaneous',
            'NWI'=>'Securities Related Item – New issues distribution',
            'ODC'=>'Overdraft charge',
            'OPT'=>'Securities Related Item – Options',
            'PCH'=>'Securities Related Item – Purchase (including STIF and Time deposits)',
            'POP'=>'Securities Related Item – Pair-off proceeds',
            'PRN'=>'Securities Related Item – Principal pay-down/pay-up',
            'REC'=>'Securities Related Item – Tax reclaim',
            'RED'=>'Securities Related Item – Redemption/Withdrawal',
            'RIG'=>'Securities Related Item – Rights',
            'RTI'=>'Returned item',
            'SAL'=>'Securities Related Item – Sale (including STIF and Time deposits)',
            'SEC'=>'Securities (used when entering a principal amount)',
            'SLE'=>'Securities Related Item – Securities lending related',
            'STO'=>'Standing order',
            'STP'=>'Securities Related Item – Stamp duty',
            'SUB'=>'Securities Related Item – Subscription',
            'SWP'=>'Securities Related Item – SWAP payment',
            'TAX'=>'Securities Related Item – Withholding tax payment',
            'TCK'=>'Travellers cheques',
            'TCM'=>'Securities Related Item – Tripartite collateral management',
            'TRA'=>'Securities Related Item – Internal transfer for own account',
            'TRF'=>'Transfer',
            'TRN'=>'Securities Related Item – Transaction fee',
            'UWC'=>'Securities Related Item – Underwriting commission',
            'VDA'=>'Value date adjustment (used with an entry made to withdraw an incorrectly dated entry – it will be followed by the correct entry with the relevant code)',
            'WAR'=>'Securities Related Item – Warrant'
        ];
        $lines[0]=str_ireplace("\r\n","//",$lines[0]);
        //$lines[0]=str_ireplace("\r","//",$lines[0]);
        //echo $this->html->pre_display($lines,"lines");
        // if (!preg_match('/(\d{6})((\d{2})(\d{2}))?(C|D)([A-Z]?)([0-9,]{1,15})/', $lines[0], $match)) {
        //     throw new \RuntimeException(sprintf('Could not parse transaction line "%s"', $lines[0]));
        // }
        // (\d{6})((\d{2})(\d{2}))?(C|D|EC|ED|RC|RD)([A-Z]?)([0-9,]{1,15}([a-zA-Z0-9]{4})(.*)(\/\/)(.*))
        // (\d{6})((\d{2})(\d{2}))?(C|D|EC|ED|RC|RD)([A-Z]?)([0-9,]{1,15})
        if (!preg_match('/(\d{6})((\d{2})(\d{2}))?(C|D|EC|ED|RC|RD)([A-Z]?)([0-9,]{1,15}([a-zA-Z0-9]{4})(.*))/', $lines[0], $match)) {
            throw new \RuntimeException(sprintf('Could not parse transaction line "%s"', $lines[0]));
        }


        $type=substr($match[8],-3);
        $type_descr=$codes[$type];
        //echo $this->html->pre_display($match,"match $type - $type_descr");
        // Parse the amount
        $amount = (float) str_replace(',', '.', $match[7]);
        if ($match[5] === 'D') {
            $dr=$amount;
            $cr=0;
            $amount *= -1;
        }
        if ($match[5] === 'ED' || $match[5] === 'RD') {
            $dr=-1*$amount;
            $cr=0;
            //$amount *= -1;
        }

        if ($match[5] === 'C') {
            $dr=0;
            $cr=$amount;
            //$amount *= -1;
        }
        if ($match[5] === 'EC' || $match[5] === 'RC') {
            $dr=0;
            $cr=-1*$amount;
            $amount *= -1;
        }

        // if($amount>0){
        //     $dr=0;
        //     $cr=$amount;
        // }else{
        //     $dr=-1*$amount;
        //     $cr=0;
        // }

        // Parse dates
        $valueDate = \DateTime::createFromFormat('ymd', $match[1]);
        $valueDate->setTime(0, 0, 0);

        $bookDate = null;
        //$bookDate = $openingBalanceDate;
        if ($match[2]) {
            // Construct book date from the month and day provided by adding the year of the value date as best guess.
            $month = intval($match[3]);
            $day = intval($match[4]);
            $bookDate = $this->getNearestDateTimeFromDayAndMonth($valueDate, $day, $month);
        }

        $add_info=str_ireplace("//", "\n", $match[9]);
        $description = isset($lines[1]) ? $lines[1] : null;

        $description=str_ireplace("\n", "", $description);
        $description=str_ireplace("\r", "", $description);
        //$description=str_ireplace("?", "\n", $description);


        $description=explode("?",$description);
        $first_line=array_shift($description);
        foreach ($description as $description_line) {
            $clean_description[]=substr($description_line,2,strlen($description_line)-2);
        }
        //$clean_description=array_map('trim',$clean_description);
        $description=$first_line." ".implode("\n",$clean_description);
        $description=str_ireplace(", ", ",", $description);
        $description=str_ireplace(",", ", ", $description);
        //$clean_description=
        $full_description=($descr[1])?$descr[1]:$descr[0];
        $full_description=$description;
        if(strlen($add_info)>1)$full_description=$add_info."\n".$full_description;
        $description_lines=explode("\n",$full_description);
        if($type_descr!='')array_unshift($description_lines, $type_descr);
        $full_description=implode("\n",array_filter($description_lines));
        //echo $this->html->pre_display($full_description,"full_description");


        //$transaction = $this->reader->createTransaction();
        // foreach ($match as $key => $value) {
        //  $transaction[match][$key]=$value;
        // }
        //$transaction[lines]=$lines;
        $transaction[amount]=$amount;
        $transaction[dr]=$dr;
        $transaction[cr]=$cr;

        $transaction[valueDate]=$valueDate->format('d.m.Y');
        $transaction[bookDate]=($bookDate)?$bookDate->format('d.m.Y'):$closingBalanceDate;
        $transaction[contraAccountNumber]=$this->contraAccountNumber($lines);
        $transaction[contraAccountName]=$this->contraAccountName($lines);
        //$transaction[add_info]=$this->description($add_info);
        $transaction[type]=$this->description($match[8]);
        $transaction[description]=$this->description($full_description);

        //$transaction[full_description]=$this->description($full_description);
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
    public function number_and_amount($text)
    {
        if (!preg_match('/(C|D)(\d{6})([A-Z]{3})([0-9,]{1,15})/', $text, $match)) {
            //throw new \RuntimeException(sprintf('Cannot parse balance: "%s"', $text));
            $balance[currency]='';
            $balance[amount]=0;
            $balance[date]='';

            return $balance;
        }

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
