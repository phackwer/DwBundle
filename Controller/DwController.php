<?php

namespace SanSIS\Core\DwBundle\Controller;

use \SanSIS\Core\BaseBundle\Controller\BaseController;

class DwController extends BaseController
{

    protected $service = 'sansis.dw.service';

    protected $indexView = 'SanSISCoreDwBundle:Dw:index.html.twig';

    // protected $createView = 'SanSISCoreDwBundle:Dw:form.html.twig'
    // protected $createRoute = 'san_sis_core_dw_create';

    // protected $editView = 'SanSISCoreDwBundle:Dw:form.html.twig';
    // protected $editRoute = 'san_sis_core_dw_edit';

    // protected $saveSuccessRoute = 'san_sis_core_dw';

    protected $monitView = 'SanSISCoreDwBundle:Dw:monit.html.twig';

    protected $drillView = 'SanSISCoreDwBundle:Dw:drillView.html.twig';
    protected $drillRoute = 'san_sis_core_dw_drill';

    protected $viewView = 'SanSISCoreDwBundle:Dw:viewView.html.twig';

    /**
     * Carrega a primeira tela do monitoramento
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function routeParamAction($routeParam)
    {
        $params = array('routeParam' => $routeParam); //array de parametros a serem passado para a interface na reinderização

        //Pega os dados da requisição e atribui a $req
        $req = $this->getRequest();
        //pegou o request , pegou o parameter bag da querystring e definiu $routeParam como o índice routeParam
        $req->query->set('routeParam', $routeParam);
        //solicita a propria controller que pegue uma instancia da service a qual ele trabalha
        $serv = $this->getService();
        // Cria um índice no array $params e atribui à variável $serv os valores para popular a view
        $params['formData'] = $serv->getFormData($req);

        return $this->render($this->monitView, $params);
    }

    /**
     * Action para popular os combos dos filtros "automagicamente" com base nos vínculos
     */
    public function getComboDataAction($routeParam)
    {
        $req = $this->getRequest();
        $req->query->set('routeParam', $routeParam);
        $data = $this->getService()->getComboData($req);
        return $this->renderJson($data);
    }

    /**
     * Action que deve ser mapeada para realizar a pesquisa e popular a grid da primeira tela - retorna um JSON
     */
    public function getGridDataAction($routeParam)
    {
        $req = $this->getRequest();
        $req->query->set('routeParam', $routeParam);
        $data = $this->getGridData();
        return $this->renderJson($data);
    }

    /**
     * Exporta dados da grid para o excel
     * @return [type] [description]
     */
    public  function exportGridToExcelAction($routeParam)
    {
        $req = $this->getRequest();
        $req->query->set('routeParam', $routeParam);

        //Obtém lista completa da service
        $arr = $this->getService()->getAllData($this->getRequest());
        $this->renderExcel($arr);
    }

    /**
     * Realiza a pesquisa paginada e retorna para o grid da primeira tela
     * @return \StdClass
     */
    public function getGridData()
    {
        //Busca a query que será utilizada na pesquisa para a grid
        $result = $this->getService()->getGridData($this->getRequest());

        return $this->preparePagedGridResult($result, 'getResultCount', 'setDrillLink');
    }

    /**
     * Prepara o resultado da pesquisa da grid para apresentação no grid de forma paginada
     *
     * @param  [array] $result      O resultado da página a ser apresentada
     * @param  [string] $countMethod O método da service que fará a contagem
     * @return [stdClass] Objeto de resposta que será convertido para JSON
     */
    public function preparePagedGridResult($result, $countMethod, $actionMethod = null)
    {
        // //pagina a ser retornada
        $page = $this->getRequest()->query->get('page', 1);
        // //quantidade de linhas a serem retornadas por página
        $rows = $this->getRequest()->query->get('rows');

        //Busca a query que será utilizada na pesquisa para a grid
        $count = $this->getService()->$countMethod($this->getRequest());

        // //Objeto de resposta
        $data = new \StdClass();
        $data->page = $page;
        // var_dump($query->getArrayResult());die;
        $data->total = ceil($count / $rows);
        $data->records = $count;

        //linhas da resposta - o método abaixo pode (e provavelmente deve)
        //ser implantado de acordo com as necessidades da aplicação
        $data->rows = $result;

        //Acrescenta o link de ações
        if ($actionMethod) {
            $data = $this->$actionMethod($data);
        }

        //máscaras para apresentação
        $this->prepareDataPresentation($data);

        return $data;
    }

    public function prepareDataPresentation(&$data)
    {
        for ($a = 0; $a < count($data->rows); $a++) {
            foreach ($data->rows[$a] as $k => $v) {
                $data->rows[$a][$k] = $this->filterPrep($k, $v);
            }
        }
    }

    public function mask($val, $mask)
    {
        $maskared = '';
        $k = 0;
        for ($i = 0; $i <= strlen($mask) - 1; $i++) {
            if ($mask[$i] == '#') {
                if (isset($val[$k])) {
                    $maskared .= $val[$k++];
                }

            } else {
                if (isset($mask[$i])) {
                    $maskared .= $mask[$i];
                }

            }
        }
        return $maskared;
    }

    public function filterPrep($k, $v)
    {
        //data
        if (strpos($v, '-') == 4 && strpos(strrev($v), '-') == 2) {
            $v = implode('/', array_reverse(explode('-', $v)));
        }
        //money
        if (strpos(strrev($v), '.') == 2 || is_float($v) || strstr($k, 'vlr_') || strstr($k, 'valor_')) {
            $negat = ((float) $v < 0) ? true : false;
            $v = number_format((float) $v, 2, ',', '.');
            $v = $negat ? '<span style="color: #FF0000">(' . str_replace('-', '', $v) . ')</span>' : '<span style="color: #006600">' . $v . '</span>';
        }

        //bool
        if ($v == 'true' || $v === true) {
            $v = 'Sim';
        } else if ($v == 'false' || $v === false) {
            $v = 'Não';
        }

        //cnpj
        if (strstr($k, 'cnpj') || strstr($k, 'CNPJ') || strstr($k, 'Cnpj')) {
            $v = $this->mask(str_pad($v, 14, 0, STR_PAD_LEFT), '##.###.###/####-##');
        }
        //cpf
        if (strstr($k, 'cpf') || strstr($k, 'CPF') || strstr($k, 'Cpf')) {
            $v = $this->mask(str_pad($v, 11, 0, STR_PAD_LEFT), '###.###.###-##');
        }

        // $v = '<div class="jqGridOverflowColumn">'.$v.'</div>';

        return $v;

    }

    /**
     * Cria o link para os níveis de Drill
     * @param array &$data Os dados do resultado da pesquisa que vão ganhar a ação de view do drill
     */
    public function setDrillLink($data)
    {

        $req = $this->getRequest();
        $routeParam = $req->get('routeParam');
        $nivel = $req->get('nivel', 1);
        $nivel++;
        $searchData = $this->getService()->getSearchData($req);
        $params = '?';
        $and = '';
        foreach ($searchData as $key => $value) {
            if ($value && $key != 'routeParam' && $key != $searchData['metrica']) {
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $params .= $and . $key . '=' . $val;
                        $and = '&';
                    }
                } else {
                    $params .= $and . $key . '=' . $value;
                }
                $and = '&';
            }

        }

        for ($a = 0; $a < count($data->rows); $a++) {
            $rowData = $and . 'valor=' . $data->rows[$a][$searchData['metrica']];
            $href = $this->generateUrl('san_sis_core_dw_drill', array(
                'routeParam' => $routeParam,
                'nivel' => $nivel,
            ));
            $data->rows[$a]['acao'] = '<a href="' . $href . $params . $rowData . '" class="icon-eye-open" title="Visualizar"></a>';

        }

        return $data;
    }

    /**
     * Apresenta a tela de Drill para N níveis
     *
     * @param  [type] $routeParam [description]
     * @param  [type] $nivel     [description]
     * @return [type]            [description]
     */
    public function drillAction($routeParam, $nivel)
    {
        $params = array(
            'routeParam' => $routeParam,
            'nivel' => $nivel,
        );

        $params['formData']['monitoramentos'] = $this->getService()->getListMonitoramento();
        $params['formData']['colunas'] = $this->getService()->getDrillColumns($routeParam, $nivel);
        $params['formData']['searchData'] = $this->getService()->getSearchData($this->getRequest());

        return $this->render($this->drillView, $params);
    }

    /**
     * Action que deve ser mapeada para realizar a pesquisa e popular uma grid
     */
    public function getDrillGridDataAction($routeParam)
    {
        $req = $this->getRequest();
        $req->query->set('routeParam', $routeParam);
        $data = $this->getDrillGridData();
        return $this->renderJson($data);
    }

    /**
     * Exporta dados da grid para o excel
     * @return [type] [description]
     */
    public  function exportDrillGridToExcelAction($routeParam)
    {
        $req = $this->getRequest();
        $req->query->set('routeParam', $routeParam);

        //Obtém lista completa da service
        $arr = $this->getService()->drillQuery($this->getRequest());
        $this->renderExcel($arr);
    }

    /**
     * Realiza a pesquisa paginada
     * @return \StdClass
     */
    public function getDrillGridData()
    {
        //Busca a query que será utilizada na pesquisa para a grid
        $result = $this->getService()->drillQuery($this->getRequest());

        $actionMethod = 'setViewLink';

        return $this->preparePagedGridResult($result, 'getResultDrillCount', $actionMethod);
    }

    /**
     * Cria o link para os níveis de Drill
     * @param array &$data Os dados do resultado da pesquisa que vão ganhar a ação de view do drill
     */
    public function setViewLink($data)
    {
        $req = $this->getRequest();
        //primeiro pegamos o schema e nome da tabela
        $monitData = $this->getService()->getMonitData($req);
        $routeParam = $req->get('routeParam');
        $nivel = $req->get('nivel', 1);
        $nivel++;
        $searchData = $this->getService()->getSearchData($req);
        $params = '?';
        $and = '';
        foreach ($searchData as $key => $value) {
            if ($value && $key != 'routeParam' && $key != $searchData['metrica']) {
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $params .= $and . $key . '=' . $val;
                        $and = '&';
                    }
                } else {
                    $params .= $and . $key . '=' . $value;
                }
                $and = '&';
            }
        }

        for ($a = 0; $a < count($data->rows); $a++) {
            $rowData = $and . 'valor=' . $data->rows[$a][$searchData['metrica']];
            $rowIdent = $monitData[0]['id_column'];
            if (!$rowIdent) {
                $rowIdent = 'id';
            }
            $codchave = $and . $rowIdent . '=' . $data->rows[$a][$rowIdent];
            if ($monitData[0]['view_route']) {
                $href = $this->generateUrl($monitData[0]['view_route']);
                $data->rows[$a]['acao'] = '<a href="' . $href . '?' . $codchave . '" class="icon-eye-open" title="Visualizar"></a>';
            }
            else {
                $href = $this->generateUrl('san_sis_core_dw_view', array(
                    'routeParam' => $routeParam,
                    'nivel' => $nivel,
                ));
                $data->rows[$a]['acao'] = '<a href="' . $href . $params . $rowData . $codchave . '" class="icon-eye-open" title="Visualizar"></a>';
            }
        }

        return $data;
    }

    /**
     * Action que deve ser mapeada para realizar a pesquisa e popular a grid da primeira tela - retorna um JSON
     */
    public function viewViewAction($routeParam, $nivel)
    {
        $req = $this->getRequest();
        $req->query->set('routeParam', $routeParam);

        $params = array(
            'formData' => $this->getService()->getFormData($req),
            'viewsData' => $this->getService()->getViewsData($req),
        );

        return $this->render($this->viewView, $params);

    }

}
