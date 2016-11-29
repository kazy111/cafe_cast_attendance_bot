<?php
// クラスオートローディング
function classAutoLoad($class_name)
{
    $path = strtolower($class_name) . '.php';
    if (file_exists($path)) {
        include $path;
        return;
    }
}

// クラスオートローディングを設定
spl_autoload_register('classAutoLoad');

// Parse date expressiopn for "[Y/]m/d" .
// return DateTime object.
// if not date expression, return FALSE.
// when year part is omitted, nearest neighbor year is used.
function parseDate($token)
{
    // calculation origin date
    $year = (int)date('Y');
    $month = (int)date('m');
    $day = (int)date('d');

    $matches = FALSE;
    if (preg_match('/^([0-9][0-9]?)\/([0-9][0-9]?)$/', $token, $matches)) {
        // m/d format
        // adjust year
        $inputMonth = (int)$matches[1];
        $range = 6;
        $prevYearStart = $month + $range + 1;
        $prevYearEnd   = 12;
        $nextYearStart = 1;
        $nextYearEnd   = $month - $range;
        if ($prevYearStart < 13 && $prevYearStart <= $inputMonth && $prevYearEnd >= $inputMonth) {
            $year--;
        } elseif ($nextYearEnd > 0 && $nextYearStart <= $inputMonth && $nextYearEnd >= $inputMonth) {
            $year++;
        }        
        $month = $inputMonth;
        $day = (int)$matches[2];
    } elseif (preg_match('/^([0-9][0-9])\/([0-9][0-9]?)\/([0-9][0-9]?)$/', $token, $matches)) {
        // yy/m/d format
        $year = (int)('20'.$matches[1]);
        $month = (int)$matches[2];
        $day = (int)$matches[3];
    } elseif (preg_match('/^([0-9][0-9][0-9][0-9])\/([0-9][0-9]?)\/([0-9][0-9]?)$/', $token, $matches)) {
        // yyyy/m/d format
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
    }

    if ($matches) {
        $strDate = sprintf('%04s',$year).'-'.sprintf('%02s',$month).'-'.sprintf('%02s',$day);
        $date = DateTime::createFromFormat('Y-m-d G:i:s', $strDate.' 00:00:00');
        return $date;
    } else {
        return FALSE;
    }

}


function tokenize($text)
{
    $text = str_replace("○", " ○ ", $text);
    $text = str_replace('×', " × ", $text);
    $text = str_replace('?', " ? ", $text);
    $text = preg_replace('/\s(?=\s)/', '', $text);
    return explode(" ", trim($text));
}


$input = '出勤 てすと 11/23 11/24? 11/27';

$tokens = tokenize($input);

$command = FALSE;
$tokenCount = count($tokens);
if ($tokenCount == 0) {
    // empty error
    return;
}
// command detect
switch ($tokens[0]) {
    case Commands\AttendCommand::NAME:
        $command = new Commands\AttendCommand();
        break;
    case Commands\CastCommand::NAME:
        $command = new Commands\CastCommand();
        break;
    default:
        // not command text
        return;
}

// parse command arguments
$index = 1;

$names = array();
$nameModes = array();
$dates = array();
$dateAttends = array();

while ($index < $tokenCount) {
    $token = $tokens[$index++];

    $dateResult = parseDate($token);
    if ($dateResult) {
        // date token
        $isAttend = Constants\AttendCode::ATTEND; // 0: undefined, 1: attend, 2: absent
        if ($index < $tokenCount) {
            if ($tokens[$index] === '○') {
                $index++;
            } elseif($tokens[$index] === '×') {
                $index++;
                $isAttend = Constants\AttendCode::ABSENT;
            } elseif($tokens[$index] === '?') {
                $index++;
                $isAttend = Constants\AttendCode::UNDEFINED;
            }
        }
        $dates[] = $dateResult;
        $dateAttends[] = $isAttend;
    } else {
        // name token
        $names[] = $token;
        if ($index < $tokenCount) {
            if($tokens[$index] === '×') {
                $index++;
                $nameModes[] = FALSE;
            } else {
                $nameModes[] = TRUE;
            }
        }
    }

}
$app = FALSE;

// now execute command
$resultText = $command->execute($app, $names, $nameModes, $dates, $dateAttends);
echo $resultText;

?>
