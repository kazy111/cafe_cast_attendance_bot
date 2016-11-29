<?php
namespace ShiftBot\Commands;

interface ICommand
{
    public function execute($app, $event, $names, $nameModes, $dates, $dateAttends);
}

?>