<?php
namespace ShiftBot\Commands;

class CalendarCommand implements ICommand
{
    const NAME = '出勤一覧';

    // return result message
    public function execute($app, $event, $names, $nameModes, $dates, $dateAttends)
    {
        $url = rtrim($app->get('settings')['siteBaseUrl'], '\\/') . '/calendar/'.date('Y/m');
        $resultMessage = $url;        
        return $resultMessage;
    }

}
