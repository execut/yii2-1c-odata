<?php
/**
 * @author Mamaev Yuriy (eXeCUT)
 * @link https://github.com/execut
 * @copyright Copyright (c) 2020 Mamaev Yuriy (eXeCUT)
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */
namespace execut\oData\docs\models;

use execut\oData\ActiveRecord;

class CheckKkmPayment extends ActiveRecord
{
    public function getCheckKkm() {
        return $this->hasOne(CheckKkm::class, [
            'Ref_Key' => 'Ref_Key',
        ]);
    }

    public static function tableName()
    {
        return 'Document_ЧекККМ_Оплата';
    }

    public function __toString()
    {
        return '#' . $this->Ref_Key;
    }

    public function getName() {
        return $this->__toString();
    }

    public function getPrimaryKey($asArray = false)
    {
        return 'Ref_Key';
    }
}
