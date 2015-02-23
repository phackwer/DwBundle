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
            $rotaParam = $entityData->query->get('rotaParam');
            $nivel = $entityData->query->get('nivel') ? $entityData->query->get('nivel') : 1;
            $formData['filtros'] = $this->getFiltros($rotaParam);
            $formData['botoes'] = $this->getBotoes($rotaParam);
            $formData['colunas'] = $this->getGridColumns($rotaParam, $nivel);
        }

        $formData['monitoramentos'] = $this->getListMonitoramento();

        return $formData;
    }

// FILTROS: BUSCA OS FILTROS NO BANCO DE DADOS
    protected function getFiltros($rotaParam)
    {
        $sql = 'SELECT
          a.dsc_monitoramento,
          a.dsc_schema,
          a.dsc_tabela,
          b.cod_filtro,
          b.dsc_campo,
          b.dsc_filtro,
          b.bln_botao,
          b.bln_filtro,
          b.num_ordem_botao,
          b.num_ordem_filtro,
          b.bln_multiplo,
          b.bln_sort_asc,
          b.cod_monitoramento
        FROM
          dashboard.tab_filtro b
          INNER JOIN dashboard.tab_monitoramento a ON (b.cod_monitoramento = a.cod_monitoramento)
        WHERE a.dsc_rota_param = \'' . $rotaParam . '\'
        AND bln_filtro = true
        ORDER BY b.num_ordem_filtro
  ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();

        $filtros = $stmt->fetchAll();

        foreach ($filtros as $key => $filtro) {
            $sql = 'SELECT DISTINCT
                ' . $filtro['dsc_campo'] . ' as valor
            FROM
              ' . $filtro['dsc_schema'] . '.' . $filtro['dsc_tabela'];

            $sql .= ' order by ' . $filtro['dsc_campo'] . ' ' . ($filtro['bln_sort_asc'] ? ' ASC ' : 'DESC');
            $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
            $stmt->execute();

            $filtros[$key]['valores'] = $stmt->fetchAll();
        }

        return $filtros;
    }

// BOTOES: BUSCA OS BOTOES NO BANCO DE DADOS
    protected function getBotoes($rotaParam)
    {
        $sql = 'SELECT
          a.dsc_monitoramento,
          a.dsc_schema,
          a.dsc_tabela,
          b.cod_filtro,
          b.dsc_campo,
          b.dsc_filtro,
          b.bln_botao,
          b.bln_filtro,
          b.num_ordem_botao,
          b.num_ordem_filtro,
          b.bln_multiplo,
          b.bln_sort_asc,
          b.cod_monitoramento
        FROM
          dashboard.tab_filtro b
          INNER JOIN dashboard.tab_monitoramento a ON (b.cod_monitoramento = a.cod_monitoramento)
        WHERE a.dsc_rota_param = \'' . $rotaParam . '\'
        AND bln_botao = true
        ORDER BY b.num_ordem_botao
  ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();

    }

    public function getMonitData(Request $req)
    {
        $sql = 'SELECT *
                    FROM dashboard.tab_monitoramento b
                     WHERE  b.dsc_rota_param = \'' . $req->query->get('rotaParam') . '\'
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
            if (!in_array($key, array('metrica', 'rotaParam', 'selected')) && $value != '') {
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
            if (!in_array($key, array('metrica', 'rotaParam', 'selected')) && $value != '' && $key != $descarte) {

                $sql = 'SELECT a.bln_sort_asc, a.dsc_campo FROM dashboard.tab_filtro a WHERE   a.cod_monitoramento = ' . $monitData[0]['cod_monitoramento'] . '  and a.dsc_campo = \'' . $key . '\'';
                $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
                $stmt->execute();
                $order = $stmt->fetchAll();
                $sql = 'select distinct ' . $key . ' from ' . $monitData[0]['dsc_schema'] . '.' . $monitData[0]['dsc_tabela'] . $where;
                $sql .= ' order by ' . $key . ' ' . ($order[0]['bln_sort_asc'] ? 'asc' : 'desc');
                $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
                $stmt->execute();
                $comboData[$key] = $stmt->fetchAll();

                // echo $sql;
            }
        }
        return $comboData;
    }

    // GRID
    protected function getGridColumns($rotaParam, $nivel)
    {
        $sql = 'SELECT *
                    FROM dashboard.tab_fatos a
                     INNER JOIN dashboard.tab_monitoramento b
                     ON a.cod_monitoramento = b.cod_monitoramento
                     WHERE a.num_nivel = ' . $nivel . ' AND b.dsc_rota_param = \'' . $rotaParam . '\'
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


    QUERYS NIVEL 1


     ********************************************************************************/

    //Busca as colunas a serem utilizadas para o Drill
    public function getDrillColumns($rotaParam, $nivel)
    {
        $nivel = 1;
        $sql = 'SELECT
                  a.dsc_schema,
                  a.dsc_tabela,
                  b.dsc_campo,
                  b.dsc_filtro
                  FROM dashboard.tab_filtro b
                 INNER JOIN dashboard.tab_monitoramento a
                 ON a.cod_monitoramento = b.cod_monitoramento
                 WHERE  a.dsc_rota_param = \'' . $rotaParam . '\'
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
        //Pegar os dados do monitoramento de acordo com a rota
        $sql = 'SELECT *
                    FROM dashboard.tab_monitoramento b
                     WHERE  b.dsc_rota_param = \'' . $searchData['rotaParam'] . '\'
                    ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();
        $monitData = $stmt->fetchAll();

        //Pega os fatos
        $sql = 'SELECT *
                    FROM
                    dashboard.tab_fatos b
                     WHERE
                      b.cod_monitoramento =  ' . $monitData[0]['cod_monitoramento'];

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $fatoData = $stmt->fetchAll();

        //Pega os dados para a grid
        $sql = 'SELECT  COUNT(*) as qtde,
                      ' . $searchData['metrica'];

        foreach ($fatoData as $key => $row) {
            $sql .= ',sum(' . $row['dsc_campo'] . ') ' . $row['dsc_campo'];
        }

        $sql .= ' FROM
                    ' . $monitData[0]['dsc_schema'] . '.' . $monitData[0]['dsc_tabela'];

        $and = ' where ';

        foreach ($searchData as $key => $value) {
            if ($key != 'metrica' && $key != 'rotaParam' && $value != '') {
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


    QUERYS NIVEL N


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
        //Pegar os dados do monitoramento de acordo com a rota
        $sql = 'SELECT *
                    FROM dashboard.tab_monitoramento b
                     WHERE  b.dsc_rota_param = \'' . $searchData['rotaParam'] . '\'
                    ';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->execute();
        $monitData = $stmt->fetchAll();

        //Pega os fatos
        $sql = 'SELECT *
                    FROM
                    dashboard.tab_fatos b
                     WHERE
                      b.cod_monitoramento =  ' . $monitData[0]['cod_monitoramento'];

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->execute();
        $fatoData = $stmt->fetchAll();

        //Pega os dados para a grid
        $sql = 'SELECT  * FROM
                    ' . $monitData[0]['dsc_schema'] . '.' . $monitData[0]['dsc_tabela'];

        $and = ' where ';

        foreach ($searchData as $key => $value) {
            if ($key != 'metrica' && $key != 'rotaParam' && $value != '') {
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
        $codMonit = $monitData[0]['cod_monitoramento']; //com este vc pega a lista das views
        $codChave = $monitData[0]['cod_chave']; //com este vc pega o nome da colune e a linha de cada view
        $codChaveValor = $req->query->get($codChave); //este é o valor que deve estar na coluna

        echo $codMonit . '<br>';
        echo $codChave . '<br>';
        echo $codChaveValor . '<br>';

        //você vai ter um array de views, faz um loop for com o resultado que veio com codMonit
        // dentro do loop vc usará o codChave

        return array();
    }

}
