<?php
namespace SanSIS\Core\DwBundle\Service;

use SanSIS\Core\BaseBundle\Service\BaseService as SBaseService;

class BaseService extends SBaseService
{
    public function getListMonitoramento()
    {
        $sql = 'select * from core_dw_monitor';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
