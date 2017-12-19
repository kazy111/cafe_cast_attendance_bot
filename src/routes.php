<?php
namespace ShiftBot;
// Routes

use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\Exception\UnknownEventTypeException;
use LINE\LINEBot\Exception\UnknownMessageTypeException;

use ShiftBot\Commands as Commands;
use ShiftBot\Constants As Constants;

function tokenize($text)
{
    $text = mb_convert_kana($text, "rnasKV", "UTF-8");
    $text = str_replace(array("\n","\r","\r\n"), ' ', $text);
    $text = str_replace(array("❌", '✖︎', '✖️'), '×', $text);
    $text = str_replace("○", " ○ ", $text);
    $text = str_replace('×', " × ", $text);
    $text = str_replace('?', " ? ", $text);
    $text = preg_replace('/\s(?=\s)/', '', $text);
    return explode(" ", trim($text));
}

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
    if (preg_match('/^([0-3]?[0-9])$/', $token, $matches)) {
        $day = (int)$matches[1];
    } elseif (preg_match('/^([0-9][0-9]?)\/([0-9][0-9]?)$/', $token, $matches)) {
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
        $date = date_create_from_format('Y-m-d G:i:s', $strDate.' 00:00:00');
        return $date;
    } else {
        return FALSE;
    }

}

function formatComment($comment) {
    $result = array();
    $tokens = mb_split('[ 　]', $comment);
    foreach ($tokens as $token) {
        if (strlen($token) > 0 && $token[0] == '#') {
            $result[] = '<a class="hashtag" href="https://twitter.com/search?q='.urlencode($token).'">'.$token.'</a>';
        } else {
            $result[] = $token;
        }
    }
    return implode(' ', $result);
}

$app->post('/callback', function ($req, $res, $args) {

    /** @var \LINE\LINEBot $bot */
    $bot = $this->bot;
    /** @var \Monolog\Logger $logger */
    $logger = $this->logger;

    $signature = $req->getHeader(HTTPHeader::LINE_SIGNATURE);
    if (empty($signature)) {
        return $res->withStatus(400, 'Bad Request');
    }
    // Check request with signature and parse request
    try {
        $events = $bot->parseEventRequest($req->getBody(), $signature[0]);
    } catch (InvalidSignatureException $e) {
        return $res->withStatus(400, 'Invalid signature');
    } catch (UnknownEventTypeException $e) {
        return $res->withStatus(400, 'Unknown event type has come');
    } catch (UnknownMessageTypeException $e) {
        return $res->withStatus(400, 'Unknown message type has come');
    } catch (InvalidEventRequestException $e) {
        return $res->withStatus(400, "Invalid event request");
    }

    try {
        foreach ($events as $event) {
            if (!($event instanceof MessageEvent)) {
                $logger->info('Non message event has come');
                continue;
            }
            if (!($event instanceof TextMessage)) {
                $logger->info('Non text message has come');
                continue;
            }

            // analyze send text
            $inputText = $event->getText();
            $logger->info('input: ' . $inputText);
            $tokens = tokenize($inputText);

            $command = FALSE;
            $tokenCount = count($tokens);
            $logger->info('token count: ' . $tokenCount);
            if ($tokenCount === 0) {
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
                case Commands\CalendarCommand::NAME:
                    $command = new Commands\CalendarCommand();
                    break;
                case Commands\CommentCommand::NAME:
                    $command = new Commands\CommentCommand();
                    break;
                default:
                    // not command text
                    return;
            }
            $logger->info('command selected');

            // parse command arguments
            $index = 1;

            $names = array();
            $nameModes = array();
            $dates = array();
            $dateAttends = array();

            while ($index < $tokenCount) {
                $token = $tokens[$index++];
                $logger->info('parse token: '. $token);

                $dateResult = parseDate($token);
                if ($dateResult) {
                    // date token
                    $isAttend = Constants\AttendCode::ATTEND;
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
                    $nameMode = TRUE;
                    if ($index < $tokenCount) {
                        if ($tokens[$index] === '×') {
                            $index++;
                            $nameMode = FALSE;
                        } else {
                            $nameMode = TRUE;
                        }
                    }
                    $nameModes[] = $nameMode;
                }

            }
            
            // now execute command
            $replyText = $command->execute($this, $event, $names, $nameModes, $dates, $dateAttends);

            $logger->info('Reply text: ' . $replyText);
            $resp = $bot->replyText($event->getReplyToken(), $replyText);
            $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
        }
    } catch (\Exception $ex) {
        $logger->error($ex->getMessage());
        //return $res->withStatus(400, $ex->getMessage());
        $replyText = _('Error: ') . $ex->getMessage();

        $logger->info('Reply error text: ' . $replyText);
        $resp = $bot->replyText($event->getReplyToken(), $replyText);
        $logger->info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }
    $res->write('OK');
    return $res;
});

$app->get('/calendar', function ($req, $res, $args) {
    return $res->withRedirect('./calendar/'.(string)date('Y').'/'.(string)date('m'));
});
$app->get('/calendar/', function ($req, $res, $args) {
    return $res->withRedirect('./'.(string)date('Y').'/'.(string)date('m'));
});

$app->get('/calendar/{year}/{month}', function ($req, $res, $args) {
    // parse target month
    $targetMonth = new \DateTime();
    $targetMonth->setDate($args['year'], $args['month'], 1);
    // calc prev/next month
    $prevMonth = clone $targetMonth;
    $prevMonth->sub(new \DateInterval('P1M'));
    $nextMonth = clone $targetMonth;
    $nextMonth->add(new \DateInterval('P1M'));

    // format for SQL paramerter
    $startDate = $targetMonth->format('Y-m-d');//sprintf('%s-%s-01', $args['year'], $args['month']);

    
    // get casts
    $casts = array();
    $urls = array();
    $pdo = $this->db;
    $sql = <<< EOM
    SELECT DISTINCT
        c.cast_id, c.display_name, c.url
     FROM casts AS c
    INNER JOIN attends AS a
       ON c.cast_id = a.cast_id
      AND a.attend_date >= :start_date
      AND a.attend_date < cast(:start_date as date) + cast('1 month' as interval)
    WHERE a.attend_type = '1'
    ORDER BY c.cast_id
EOM;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':start_date' => $startDate));
    while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $casts[] = $result['display_name'];
        $urls[] = $result['url'];
    }
    $args['casts'] = $casts;
    $args['urls'] = $urls;


    // get comments & prepare calendar
    $comments = array();
    $calendar = array();
    $sql = <<< EOM
    SELECT
        cal.date, COALESCE(c.comment, '') as comment
    FROM (
        SELECT
            cast(:start_date as date) + s.i AS date
        FROM
            generate_series( 0, 31 ) as s(i)
        WHERE
            cast(:start_date as date) + s.i < cast(:start_date as date) + cast('1 month' as interval)
    ) AS cal
    LEFT JOIN comments AS c
       ON cal.date = c.comment_date
    ORDER BY cal.date
EOM;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':start_date' => $startDate));
    while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $comments[] = formatComment($result['comment']);
        $calendar[] = array();
    }
    $args['comments'] = $comments;


    // get attends as calendar
    $sql = <<< EOM
    SELECT
        cal.date, c.cast_id, COALESCE(a.attend_type, '0') as attend_type
    FROM (
        SELECT
            cast(:start_date as date) + s.i AS date
        FROM
            generate_series( 0, 31 ) as s(i)
        WHERE
            cast(:start_date as date) + s.i < cast(:start_date as date) + cast('1 month' as interval)
    ) AS cal
    CROSS JOIN (
        SELECT DISTINCT
            ca.cast_id, ca.display_name
         FROM casts AS ca
        INNER JOIN attends AS a
           ON ca.cast_id = a.cast_id
          AND a.attend_date >= :start_date
          AND a.attend_date < cast(:start_date as date) + cast('1 month' as interval)
        WHERE a.attend_type = '1'
    ) AS c
    LEFT JOIN attends AS a
       ON cal.date = a.attend_date
      AND c.cast_id = a.cast_id
    ORDER BY cal.date, c.cast_id
EOM;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':start_date' => $startDate));
    $currDate = FALSE;
    $index = -1;
    while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        if ($currDate !== $result['date']) {
            $currDate = $result['date'];
            $index++;
        }
        $calendar[$index][] = $result['attend_type'];
    }
    $args['calendar'] = $calendar;

    $args['targetMonth'] = $targetMonth;
    $args['prevMonth'] = $prevMonth;
    $args['nextMonth'] = $nextMonth;
    $args['title'] = $this->get('settings')['title'];
    // Render calendar view
    return $this->renderer->render($res, 'calendar.phtml', $args);
});


$app->get('/about', function ($req, $res, $args) {
    $args['title'] = $this->get('settings')['title'];
    // Render about view
    return $this->renderer->render($res, 'about.phtml', $args);
});


$app->get('/ical', function ($req, $res, $args) {
    $vCalendar = new \Eluceo\iCal\Component\Calendar('kazy111.info');

    $pdo = $this->db;
    $sql = <<< EOM
    SELECT
        c.comment_date, c.comment
    FROM comments AS c
    ORDER BY c.comment_date
EOM;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    while ($result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $vEvent = new \Eluceo\iCal\Component\Event();
        $comment_date = \DateTime::createFromFormat('Y-m-d', $result['comment_date']);
        $vEvent
            ->setDtStart($comment_date)
            ->setDtEnd($comment_date)
            ->setNoTime(true)
            ->setSummary($result['comment']);
        $vCalendar->addComponent($vEvent);
    }

    //header('Content-Type: text/calendar; charset=utf-8');
    //header('Content-Disposition: attachment; filename="cal.ics"');
    return $res->withHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->withHeader('Content-Disposition', 'attachment; filename="cal.ics"')
        ->write($vCalendar->render());
    //return $this->renderer->render($res, 'ical.phtml', $args);
});