<?php
namespace SanSIS\Core\DwBundle\Service;

use \Symfony\Component\HttpFoundation\Request;

/**
 * CONTÉM AS REGRAS DE NEGÓCIO PARA DW
 */
class DwService extends BaseService
{
    /**
     * [getFormData CARREGA OS DADOS DE FORMULÁRIOS PARA EXIBIÇÃO INICIAL]
     * @param  \Symfony\Component\HttpFoundation\Request $entityData [CONTÉM DADOS REFERENTES À REQUISIÇÃO FEITA PARA A SERVICE]
     * @return array             array contendo os dados a serem apresentados no formulário
     */

    public function getFormData($entityData = null)
    {
        $formData = array();

        if (!is_null($entityData)) {
            $routeParam = $entityData->query->get('routeParam');
            $level = $entityData->query->get('level') ? $entityData->query->get('level') : 1;
            $formData['filtros'] = $this->getFiltros($routeParam);
            $formData['botoes'] = $this->getBotoes($routeParam);
            $formData['colunas'] = $this->getGridColumns($routeParam, $level);
        }

        $formData['monitoramentos'] = $this->getListMonitoramento();

        return $formData;
    }

// FILTROS: BUSCA OS FILTROS NO BANCO DE DADOS
    protected function getFiltros($routeParam)
    {
        $sql = 'SELECT
          a.name,
          a.schema_name,
          a.dataview_name,
          b.id,
          b.dataview_column,
          b.dataview_column_type,
          b.screen_name,
          b.is_metric,
          b.is_filter,
          b.screen_order,
          b.is_multiple,
          b.filter_sort,
          b.id
        FROM
          core_dw_filter b
          INNER JOIN core_dw_monitor a ON (a.id = b.monitor_id)
        WHERE a.route_param = \'' . $routeParam . '\'
        AND is_filter = true
        ORDER BY b.screen_order';

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();

        $filtros = $stmt->fetchAll();

        foreach ($filtros as $key => $filtro) {
            $sql = 'SELECT DISTINCT
                ' . $filtro['dataview_column'] . ' as valor
            FROM
              ' . $filtro['schema_name'] . '.' . $filtro['dataview_name'];

            $sql .= ' order by ' . $filtro['dataview_column'] . ' ' . ($filtro['filter_sort'] ? ' ASC ' : 'DESC');
            $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
            $stmt->execute();

            $filtros[$key]['valores'] = $stmt->fetchAll();
        }

        return $filtros;
    }

// BOTOES: BUSCA OS BOTOES NO BANCO DE DADOS
    protected function getBotoes($routeParam)
    {
        $sql = 'SELECT
          a.name,
          a.schema_name,
          a.dataview_name,
          b.id,
          b.dataview_column,
          b.dataview_column_type,
          b.screen_name,
          b.is_metric,
          b.is_filter,
          b.screen_order,
          b.is_multiple,
          b.filter_sort,
          b.id
        FROM
          core_dw_filter b
          INNER JOIN core_dw_monitor a ON (a.id = b.monitor_id)
        WHERE a.route_param = \'' . $routeParam . '\'
        AND is_metric = true
        ORDER BY b.screen_order';

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();

    }

    public function getMonitData(Request $req)
    {
        $sql = 'SELECT *
                    FROM core_dw_monitor b
                     WHERE  b.route_param = \'' . $req->query->get('routeParam') . '\'
                    ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Pega os dados das combos de maneira encadeada
     */
    public function getComboData(Request $req)
    {
        //primeiro pegamos o schema e nome da tabela
        $monitData = $this->getMonitData($req);

        //Agora construímos um where para o que foi submetido
        $keys = $req->query->keys();
        //Campo que será ignorado vêementemente descartado
        $descarte = $req->query->get('selected');

        $where = ' where ';
        $and = '';

        foreach ($keys as $key) {
            $value = $req->query->get($key);
            if (!in_array($key, array('metrica', 'routeParam', 'selected')) && $value != '') {
                if (is_array($value)) {
                    $or = '';
                    $where .= $and . '  ( ';
                    foreach ($value as $val) {
                        $where .= $or . $key . ' = \'' . $val . '\'';
                        $or = ' or ';
                    }
                    $where .= ')';
                } else {
                    $where .= $and . $key . ' = \'' . $value . '\'';
                }
                $and = ' and ';
            }
        }

        if ($where == ' where ') {
            $where = '';
        }

        $comboData = array();

        foreach ($keys as $key) {
            if (!in_array($key, array('metrica', 'routeParam', 'selected')) && $value != '' && $key != $descarte) {

                $sql = 'SELECT a.filter_sort, a.dataview_column FROM core_dw_filter a WHERE   a.monitor_id = ' . $monitData[0]['id'] . '  and a.dataview_column = \'' . $key . '\'';
                $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
                $stmt->execute();
                $order = $stmt->fetchAll();
                $sql = 'select distinct ' . $key . ' from ' . $monitData[0]['schema_name'] . '.' . $monitData[0]['dataview_name'] . $where;
                $sql .= ' order by ' . $key . ' ' . ($order[0]['filter_sort'] ? 'asc' : 'desc');
                $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
                $stmt->execute();
                $comboData[$key] = $stmt->fetchAll();

                // echo $sql;
            }
        }
        return $comboData;
    }

    // GRID
    protected function getGridColumns($routeParam, $level)
    {
        $sql = 'SELECT *
                    FROM core_dw_filter a
                     INNER JOIN core_dw_monitor b
                     ON a.monitor_id = b.id
                     WHERE a.level = ' . $level . ' AND
                     a.is_fact = true AND
                     b.route_param = \'' . $routeParam . '\'
                    ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * filtra os dados submetidos para uso pelas queries
     * @param  Request $req
     * @return array
     */
    public function getSearchData(Request $req)
    {
        $keys = $req->query->keys();
        $searchData = array();
        foreach ($keys as $key) {
            if ($req->query->has($key)) {
                $searchData[$key] = $req->query->get($key);

                if ($key == 'valor' && !isset($searchData[$searchData['metrica']])) {
                    $searchData[$searchData['metrica']] = $req->query->get($key);
                    unset($searchData['valor']);

                }
            }
        }

        unset($searchData['rows']);
        unset($searchData['page']);
        unset($searchData['sidx']);
        unset($searchData['sord']);
        unset($searchData['nd']);
        unset($searchData['_search']);

        return $searchData;
    }

    /********************************************************************************


    QUERYS level 1


     ********************************************************************************/

    //Busca as colunas a serem utilizadas para o Drill
    public function getDrillColumns($routeParam, $level)
    {
        $level = 1;
        $sql = 'SELECT
                  a.schema_name,
                  a.dataview_name,
                  b.dataview_column,
                  b.screen_name
                  FROM core_dw_filter b
                 INNER JOIN core_dw_monitor a
                 ON b.monitor_id = a.id
                 WHERE  a.route_param = \'' . $routeParam . '\'
                    ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getGridData(Request $req)
    {
        $orderby = null;
        $order = null;
        if ($req->query->get('sidx')) {
            $orderby = $req->query->get('sidx');
            $order = $req->query->get('sord');
        }

        $page = null;
        $rows = null;
        if ($req->query->get('page', 1)) {
            $rows = $req->query->get('rows');
            $page = $req->query->get('page');
        }

        $searchData = $this->getSearchData($req);

        $sql = $this->getPagedSearchQuery($searchData, $orderby, $order, $page, $rows);

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $gridData = $stmt->fetchAll();

        return $gridData;
    }

    public function getAllData(Request $req)
    {
        $orderby = null;
        $order = null;
        if ($req->query->get('sidx')) {
            $orderby = $req->query->get('sidx');
            $order = $req->query->get('sord');
        }

        $page = null;
        $rows = null;
        if ($req->query->get('page', 1)) {
            $rows = $req->query->get('rows');
            $page = $req->query->get('page');
        }

        $searchData = $this->getSearchData($req);

        $sql = $this->getFullSearchQuery($searchData, $orderby, $order, $page, $rows);

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $gridData = $stmt->fetchAll();

        return $gridData;
    }

    public function getResultCount(Request $req)
    {
        $searchData = $this->getSearchData($req);

        $sql = $this->getFullSearchQuery($searchData);

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $gridData = $stmt->fetchAll();

        return count($gridData);
    }

    public function getFullSearchQuery(&$searchData, $orderby = null, $order = null, $page = null, $rows = null)
    {
        //Pegar os dados do monitoramento de acordo com a route
        $sql = 'SELECT *
                    FROM core_dw_monitor b
                     WHERE  b.route_param = \'' . $searchData['routeParam'] . '\'
                    ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();
        $monitData = $stmt->fetchAll();

        //Pega os fatos
        $sql = 'SELECT *
                    FROM
                    core_dw_filter b
                     WHERE
                      b.is_fact = true
                      AND
                      b.monitor_id =  ' . $monitData[0]['id'].'
                      ORDER BY screen_order';

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $fatoData = $stmt->fetchAll();

        //Pega os dados para a grid
        $sql = 'SELECT  COUNT(*) as qtde,
                      ' . $searchData['metrica'];

        foreach ($fatoData as $key => $row) {
            if ($row['dataview_column_type'] == 'integer' || $row['dataview_column_type'] == 'float' || $row['dataview_column_type'] == 'money')
                $sql .= ',sum(' . $row['dataview_column'] . ') ' . $row['dataview_column'];
            else
                $sql .= ','.$row['dataview_column'];
        }

        $sql .= ' FROM
                    ' . $monitData[0]['schema_name'] . '.' . $monitData[0]['dataview_name'];

        $and = ' where ';

        foreach ($searchData as $key => $value) {
            if ($key != 'metrica' && $key != 'routeParam' && $value != '') {
                if (is_array($value)) {
                    $or = '';
                    $sql .= $and . '  ( ';
                    foreach ($value as $val) {
                        $sql .= $or . $key . ' = \'' . $val . '\'';
                        $or = ' or ';
                    }
                    $sql .= ')';
                    $and = ' and ';
                } else {
                    $sql .= $and . ' ' . $key . ' = \'' . $value . '\'';
                    $and = ' and ';
                }
            }
        }

        $sql .= ' group by
                     ' . $searchData['metrica'];

        if ($orderby) {
            $sql .= ' order by ' . ($orderby) . ' ' . $order;
        }

        return $sql;
    }

    public function getPagedSearchQuery(&$searchData, $orderby = null, $order = null, $page = null, $rows = null)
    {
        $sql = $this->getFullSearchQuery($searchData, $orderby, $order, $page, $rows);

        if ($rows) {
            $sql .= ' limit ' . ($rows);

            if ($page) {
                $sql .= ' offset ' . ($page * $rows - $rows);
            }
        }

        return $sql;
    }

    /********************************************************************************


    QUERYS level N


     ********************************************************************************/

    public function drillQuery(Request $req)
    {
        $orderby = null;
        $order = null;
        if ($req->query->get('sidx')) {
            $orderby = $req->query->get('sidx');
            $order = $req->query->get('sord');
        }

        $page = null;
        $rows = null;
        if ($req->query->get('page', 1)) {
            $rows = $req->query->get('rows');
            $page = $req->query->get('page');
        }

        $searchData = $this->getSearchData($req);

        $sql = $this->getPagedDrillQuery($searchData, $orderby, $order, $page, $rows);

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $gridData = $stmt->fetchAll();

        return $gridData;
    }

    public function drillFullQuery(Request $req)
    {
        $orderby = null;
        $order = null;
        if ($req->query->get('sidx')) {
            $orderby = $req->query->get('sidx');
            $order = $req->query->get('sord');
        }

        $page = null;
        $rows = null;
        if ($req->query->get('page', 1)) {
            $rows = $req->query->get('rows');
            $page = $req->query->get('page');
        }

        $searchData = $this->getSearchData($req);

        $sql = $this->getFullDrillQuery($searchData, $orderby, $order, $page, $rows);

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $gridData = $stmt->fetchAll();

        return $gridData;
    }

    public function getResultDrillCount(Request $req)
    {
        $searchData = $this->getSearchData($req);

        $sql = $this->getFullDrillQuery($searchData);

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $gridData = $stmt->fetchAll();

        return count($gridData);
    }

    public function getFullDrillQuery(&$searchData, $orderby = null, $order = null, $page = null, $rows = null)
    {
        //Pegar os dados do monitoramento de acordo com a route
        $sql = 'SELECT *
                    FROM core_dw_monitor b
                     WHERE  b.route_param = \'' . $searchData['routeParam'] . '\'
                    ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();
        $monitData = $stmt->fetchAll();

        //Pega os fatos
        $sql = 'SELECT *
                    FROM
                    core_dw_filter b
                     WHERE
                      b.is_fact = true
                      AND
                      b.monitor_id =  ' . $monitData[0]['id'].'
                      ORDER BY screen_order';

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $fatoData = $stmt->fetchAll();

        //Pega os dados para a grid
        $sql = 'SELECT  * FROM
                    ' . $monitData[0]['schema_name'] . '.' . $monitData[0]['dataview_name'];

        $and = ' where ';

        foreach ($searchData as $key => $value) {
            if ($key != 'metrica' && $key != 'routeParam' && $value != '') {
                if (is_array($value)) {
                    $or = '';
                    $sql .= $and . '  ( ';
                    foreach ($value as $val) {
                        $sql .= $or . $key . ' = \'' . $val . '\'';
                        $or = ' or ';
                    }
                    $sql .= ')';
                    $and = ' and ';
                } else {
                    $sql .= $and . ' ' . $key . ' = \'' . $value . '\'';
                    $and = ' and ';
                }
            }
        }

        if ($orderby) {
            $sql .= ' order by ' . ($orderby) . ' ' . $order;
        }

        return $sql;
    }

    public function getPagedDrillQuery(&$searchData, $orderby = null, $order = null, $page = null, $rows = null)
    {
        $sql = $this->getFullDrillQuery($searchData, $orderby, $order, $page, $rows);

        if ($rows) {
            $sql .= ' limit ' . ($rows);

            if ($page) {
                $sql .= ' offset ' . ($page * $rows - $rows);
            }
        }

        return $sql;
    }

    public function getViewsData(Request $req)
    {
        //MATERA! Fazer as queries para pegar as views do monitoramento com o parametro passado do identificador/chave
        $monitData = $this->getMonitData($req);
        $codMonit = $monitData[0]['id']; //com este vc pega a lista das views
        $codChave = $monitData[0]['id_column']; //com este vc pega o nome da colune e a linha de cada view
        $codChaveValor = $req->query->get($codChave); //este é o valor que deve estar na coluna

        // echo $codMonit . '<br>';
        // echo $codChave . '<br>';
        // echo $codChaveValor . '<br>';

        //você vai ter um array de views, faz um loop for com o resultado que veio com codMonit
        // dentro do loop vc usará o codChave

        return array();
    }

}
