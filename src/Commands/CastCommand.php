<?php
namespace ShiftBot\Commands;

class CastCommand implements ICommand
{
    const NAME = 'キャスト';

    private function checkInput($names, $dates, $dateAttends)
    {
        if (count($names) === 0) {
            // no name
            throw new \Exception(_('no name'));
        }
        if (count($dates) > 0) {
            // invalid arguments
            throw new \Exception(_('invalid arguments'));
        }
    }

    public function execute($app, $event, $names, $nameModes, $dates, $dateAttends)
    {
        $this->checkInput($names, $dates, $dateAttends);

        $regCount = 0;
        $castIds = array();
        $pdo = $app->db;
        $userId = '';//$event->getUserId();

        // try get cast id from name master
        foreach ($names as $name) {
            // get for name
            $sql = 'SELECT n.cast_id FROM names AS n CROSS JOIN suffixes AS s WHERE n.name || s.suffix = :name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':name' => $name));
            $result = $stmt->fetchAll();
            $castId = (count($result) === 0 ? FALSE : $result[0]['cast_id']);
            $castIds[] = $castId;
            if ($castId !== FALSE) {
                $regCount++;
            }
        }

        try {
            // begin tran
            $pdo->beginTransaction();
            
            $castId = FALSE;
            if ($regCount === 1 && $castIds[0] !== FALSE) {
                // if first name only has cast id, then use the id
                $castId = $castIds[0];
            } elseif ($regCount === 0) {
                // if no register cast id, create new cast id
                $sql = 'INSERT INTO casts (display_name, create_time, create_id) VALUES (:display_name, now(), :userid) RETURNING cast_id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array(':display_name' => $names[0], ':userid' => $userId));
                $result = $stmt->fetchAll();
                if (count($result) === 0) {
                    throw new \Exception(_('internal error - cannot add cast'));
                }
                $castId = $result[0]['cast_id'];
            } else {
                // too many registerd names error
                throw new \Exception(_('multiple names already registered.'));
            }
            echo $castId;

            // register name master
            $insSql = 'INSERT INTO names (name, cast_id, create_time, create_id) VALUES (:name, :cast_id, now(), :userid)';
            $insStmt = $pdo->prepare($insSql);
            $delSql = 'DELETE FROM names WHERE name = :name';
            $delStmt = $pdo->prepare($delSql);
            $countSql = 'SELECT COUNT(*) AS c FROM names WHERE cast_id = :cast_id';
            $countStmt = $pdo->prepare($countSql);
            $delCastSql = 'DELETE FROM casts WHERE cast_id = :cast_id';
            $delCastStmt = $pdo->prepare($delCastSql);
            $delAtndSql = 'DELETE FROM attends WHERE cast_id = :cast_id';
            $delAtndStmt = $pdo->prepare($delAtndSql);

            for ($i = 0; $i < count($names); $i++) {
                if (($nameModes[$i] === TRUE && $castIds[$i] !== FALSE)  || ($nameModes[$i] === FALSE && $castIds[$i] === FALSE)) {
                    continue;
                }
                if ($nameModes[$i]){
                    $insStmt->execute(array(':name' => $names[$i], ':cast_id' => $castId, ':userid' => $userId));
                } else {
                    $delStmt->execute(array(':name' => $names[$i]));

                    // if no name for the cast_id, then delete cast and attends
                    $countStmt->execute(array(':cast_id' => $castId));
                    $countResult = $countStmt->fetchAll();
                    if ($countResult[0]['c'] == 0) {
                        $delAtndStmt->execute(array(':cast_id' => $castId));
                        $delCastStmt->execute(array(':cast_id' => $castId));
                    }

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
