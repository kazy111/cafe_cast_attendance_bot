<?php
namespace ShiftBot\Commands;

class AttendCommand implements ICommand
{
    const NAME = '出勤';

    private function checkInput($names, $dates, $dateAttends)
    {
        if (count($names) > 1) {
            // too many names
            throw new \Exception(_('too many names'));
        }
        if (count($dates) !== count($dateAttends)) {
            // data count mismatch error
            throw new \Exception(_('internal error - dates and attends data count mismatch'));
        }
    }

    // return result message
    public function execute($app, $event, $names, $nameModes, $dates, $dateAttends)
    {
        $this->checkInput($names, $dates, $dateAttends);

        $pdo = $app->db;

        if (count($names) === 0 && count($dates) === 0) {
            // list today's' attends
            $sql = <<< EOM
            SELECT
                n.display_name
             FROM casts AS n
            INNER JOIN attends AS a
               ON n.cast_id = a.cast_id
            WHERE a.attend_date = :date
              AND a.attend_type = '1'
            ORDER BY n.cast_id
EOM;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':date' => date('Y-m-d')));
            $result = $stmt->fetchAll();
            $resultMessage = _("today's casts: ");
            if (count($result) === 0) {
                $resultMessage .= _('None');
            } else {
                foreach ($result as $row) {
                    $resultMessage .= $row['display_name'].' ';
                }
            }

        } elseif (count($names) === 1 && count($dates) === 0) {
            // list schedule of name
            $targetName = $names[0];

            // detect cast id
            $sql = 'SELECT n.cast_id FROM names AS n CROSS JOIN suffixes AS s WHERE n.name || s.suffix = :name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':name' => $targetName));
            $result = $stmt->fetchAll();
            if (count($result) === 0) {
                throw new \Exception(_('cast not found'));
            }
            $castId = $result[0]['cast_id'];

            // get display name
            $sql = 'SELECT display_name FROM casts WHERE cast_id = :cast_id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':cast_id' => $castId));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $displayName = $result['display_name'];

            // get attends
            $sql = <<< EOM
            SELECT attend_date
             FROM attends
            WHERE cast_id = :cast_id
              AND attend_date >= CURRENT_DATE
              AND attend_type = '1'
            ORDER BY attend_date LIMIT 10
EOM;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':cast_id' => $castId));
            $result = $stmt->fetchAll();
            
            $resultMessage = sprintf(_("%s's schedule: "), $displayName);
            if (count($result) === 0) {
                $resultMessage .= _('TBD');
            } else {
                foreach ($result as $row) {
                    $resultMessage .= \DateTime::createFromFormat('Y-m-d', $row['attend_date'])->format('m/d') . ', ';
                }
                $resultMessage = substr($resultMessage, 0, strlen($resultMessage) - 2);
            }
        } else if (count($names) === 0 && count($dates) > 0) {

            if (count($dates) > 1) {
                // too many dates
                throw new \Exception(_('too many dates'));
            }

            // list target date attends
            $sql = <<< EOM
            SELECT
                n.display_name
             FROM casts AS n
            INNER JOIN attends AS a
               ON n.cast_id = a.cast_id
            WHERE a.attend_date = :date
              AND a.attend_type = '1'
            ORDER BY n.cast_id
EOM;
            $stmt = $pdo->prepare($sql);
            $targetDate = $dates[0]->format('Y-m-d');
            $stmt->execute(array(':date' => $targetDate));
            $result = $stmt->fetchAll();
            $resultMessage = sprintf(_('Attendance on %s: '), $dates[0]->format('Y/m/d'));
            if (count($result) === 0) {
                $resultMessage .= _('None');
            } else {
                foreach ($result as $row) {
                    $resultMessage .= $row['display_name'].' ';
                }
            }

        } else {
            // register                
            $targetName = $names[0];

            // get cast id
            $sql = 'SELECT n.cast_id FROM names AS n CROSS JOIN suffixes AS s WHERE n.name || s.suffix = :name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':name' => $targetName));
            $result = $stmt->fetchAll();
            if (count($result) === 0) {
                throw new \Exception(_('cast not found'));
            }
            $castId = $result[0]['cast_id'];

            try {
                // begin tran
                $pdo->beginTransaction();

                $selectSql = 'SELECT COUNT(*) AS c FROM attends WHERE cast_id = :cast_id AND attend_date = :target_date';
                $selectStmt = $pdo->prepare($selectSql);

                $deleteSql = 'DELETE FROM attends WHERE cast_id = :cast_id AND attend_date = :target_date';
                $deleteStmt = $pdo->prepare($deleteSql);

                $insertSql = 'INSERT INTO attends (cast_id, attend_date, attend_type, create_time, create_id) VALUES (:cast_id, :target_date, :attend_type, now(), :id)';
                $insertStmt = $pdo->prepare($insertSql);

                $updateSql = 'UPDATE attends SET attend_type = :attend_type, update_time = now(), update_id = :id WHERE cast_id = :cast_id AND attend_date = :target_date';
                $updateStmt = $pdo->prepare($updateSql);

                $userId = '';//$event->getUserId();

                // upsert attend statuses
                for ($i = 0; $i < count($dates); $i++) {
                    $targetDate = $dates[$i]->format('Y-m-d');
                    
                    $selectStmt->execute(array(':cast_id' => $castId, ':target_date' => $targetDate));
                    $result = $selectStmt->fetchAll();
                    $dataCount = (count($result) === 0 ? 0 : $result[0]['c']);

                    switch ($dateAttends[$i]) {
                        case \ShiftBot\Constants\AttendCode::UNDEFINED:
                            if ($dataCount > 0) {
                                $deleteStmt->execute(array(':cast_id' => $castId, ':target_date' => $targetDate));
                            }
                            break;
                        case \ShiftBot\Constants\AttendCode::ATTEND:
                            if ($dataCount > 0) {
                                $updateStmt->execute(array(':cast_id' => $castId, ':target_date' => $targetDate, ':attend_type' => '1', ':id' => $userId));
                            } else {                                
                                $insertStmt->execute(array(':cast_id' => $castId, ':target_date' => $targetDate, ':attend_type' => '1', ':id' => $userId));
                            }
                            break;
                        case \ShiftBot\Constants\AttendCode::ABSENT:
                            if ($dataCount > 0) {
                                $updateStmt->execute(array(':cast_id' => $castId, ':target_date' => $targetDate, ':attend_type' => '2', ':id' => $userId));
                            } else {                                
                                $insertStmt->execute(array(':cast_id' => $castId, ':target_date' => $targetDate, ':attend_type' => '2', ':id' => $userId));
                            }
                            break;
                    }

                }
                $pdo->commit();
                $resultMessage = _('register completed.');
            } catch (\Exception $ex) {
                $pdo->rollBack();
                throw $ex;
            }
        }

        return $resultMessage;
    }
}
?>