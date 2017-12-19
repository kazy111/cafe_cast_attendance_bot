<?php
namespace ShiftBot\Commands;

class CommentCommand implements ICommand
{
    const NAME = 'コメント';

    private function checkInput($names, $dates)
    {
        if (count($dates) !== 1) {
            // too many dates
            throw new \Exception(_('too many dates'));
        }
    }

    // return result message
    public function execute($app, $event, $names, $nameModes, $dates, $dateAttends)
    {
        $this->checkInput($names, $dates);

        $pdo = $app->db;

        // register
        $targetDate = $dates[0]->format('Y-m-d');

        try {
            // begin tran
            $pdo->beginTransaction();

            $selectSql = 'SELECT COUNT(*) AS c FROM comments WHERE comment_date = :target_date';
            $selectStmt = $pdo->prepare($selectSql);

            $deleteSql = 'DELETE FROM comments WHERE comment_date = :target_date';
            $deleteStmt = $pdo->prepare($deleteSql);

            $insertSql = 'INSERT INTO comments (comment_date, comment, create_time, create_id) VALUES (:target_date, :comment, now(), :id)';
            $insertStmt = $pdo->prepare($insertSql);

            $updateSql = 'UPDATE comments SET comment = :comment, update_time = now(), update_id = :id WHERE comment_date = :target_date';
            $updateStmt = $pdo->prepare($updateSql);

            $userId = '';//$event->getUserId();

            // upsert attend statuses
            if (count($names) === 0) {
                $deleteStmt->execute(array(':target_date' => $targetDate));
            } else {
                $selectStmt->execute(array(':target_date' => $targetDate));
                $result = $selectStmt->fetchAll();
                $dataCount = (count($result) === 0 ? 0 : $result[0]['c']);

                $comment = implode(' ', $names);
                if ($dataCount > 0) {
                    $updateStmt->execute(array(':target_date' => $targetDate, ':comment' => $comment, ':id' => $userId));
                } else {                                
                    $insertStmt->execute(array(':target_date' => $targetDate, ':comment' => $comment, ':id' => $userId));
                }
            }

            $pdo->commit();
            $resultMessage = _('register completed.');
        } catch (\Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        return $resultMessage;
    }
}
?>