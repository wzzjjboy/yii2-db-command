<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 2018/2/9
 * Time: 14:20
 * 解决mysql在cli模式下 gone away的问题
 */

namespace alan\yii2\db;

use yii\db\Exception;

class Command extends \yii\db\Command
{

    protected $_pendingParams = [];

    protected $_pendingParams2 = [];

    public $sleepTime = 1;

    public function execute()
    {
        try {
            return parent::execute();
        } catch (Exception $e) {
            if ($this->isGoAway()) {
                $this->db->close();
                $this->db->open();
                $this->pdoStatement = null;
                sleep($this->sleepTime);
                return parent::execute();
            } else {
                $this->log($e);
                throw $e;
            }
        }
    }


    protected function queryInternal($method, $fetchMode = null)
    {
        try {
            return parent::queryInternal($method, $fetchMode);
        } catch (Exception $e) {
            if ($this->isGoAway()) {
                $this->db->close();
                $this->db->open();
                $this->pdoStatement = null;
                sleep($this->sleepTime);
                return parent::queryInternal($method, $fetchMode);
            } else {
                $this->log($e);
                throw $e;
            }
        }
    }

    /**
     * @return bool
     */
    protected function isGoAway()
    {
        if (!$this->pdoStatement){
            return false;
        }

        $msg = $this->pdoStatement->errorInfo();
        if (($code = ($msg[1] ?? false)) && ($code == 2006 || $code == 2013)) {
            return true;
        }

        return false;
    }

    protected function bindPendingParams()
    {
        foreach ($this->_pendingParams ?: $this->_pendingParams2 as $name => $value) {
            $this->pdoStatement->bindValue($name, $value[0], $value[1]);
        }
        $this->_pendingParams = [];
    }

    public function bindValues($values)
    {
        if (empty($values)) {
            return $this;
        }

        $schema = $this->db->getSchema();
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $this->_pendingParams2[$name] = $this->_pendingParams[$name] = $value;
                $this->params[$name] = $value[0];
            } else {
                $type = $schema->getPdoType($value);
                $this->_pendingParams2[$name] = $this->_pendingParams[$name] = [$value, $type];
                $this->params[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * @param Exception $e
     */
    private function log($e)
    {
        $t = date("y-m-d H:i:s");
    }

}