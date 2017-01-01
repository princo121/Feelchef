<?php
namespace Portale\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZendService\Api\Api;
use Zend\View\Model\JsonModel;
use Zend\Http\PhpEnvironment\Request;
use ArrayObject;
use Zend\Form\Annotation\Object;
use Zend\I18n\Translator\Translator;
use Zend\I18n\View\Helper\Translate;

/**
 * 
 * @author Valenti Mirko
 * @version 1.0
 * Proposte FeelChef list and detail
 */
class ProposteController extends AbstractActionController
{

    /**
     *
     * @var searchForm
     */
    protected $searchForm;

    /**
     *
     * @var quickreservationform
     */
    protected $quickreservationform;

    /**
     *
     * @var tipologiaTable
     */
    protected $tipologiaTable;

    /**
     *
     * @var propostaTable
     */
    protected $propostaTable;

    /**
     *
     * @var servizioTable
     */
    protected $servizioTable;

    /**
     *
     * @var chiamata
     */
    protected $chiamata;

    /**
     * 
     * @var date_international
     */
    protected $date_international;
    
    /**
     * List of menù
     */
    public function indexAction()
    {
        $searchform = $this->getSearchForm();
        $tipologia = $this->getTipologiaTable()->fetchAll();
        $servizi = $this->getServizioTable()->fetchAll();
        $request = $this->getRequest();
        if ($this->getRequest()->getQuery('data')) {
            $searchform->get('data')->setValue($this->getRequest()->getQuery('data'));
            $searchform->get('ora')->setValue($this->getRequest()
                ->getQuery('ora'));
        }
        $proposte = $this->proposteAction();
        return array(
            'searchform' => $searchform,
            'tipologia' => $tipologia,
            'servizi' => $servizi,
            'proposte' => $proposte->html,
            'filtri' => $proposte->filtri,
            'paginator' => $proposte->paginator,
            'status' => $proposte->status
        );
    }

    /**
     * return Array of menù
     */
    public function proposteAction()
    {
    	if ($this->getRequest()->getQuery('data')){
    		$this->date_format_international($this->getRequest()->getQuery('data'));
    	}    	 
        $filtro = array(
            'citta' => $this->getRequest()->getQuery('citta'),
            'prezzo_min' => $this->getRequest()->getQuery('prezzo_min'),
            'prezzo_max' => $this->getRequest()->getQuery('prezzo_max'),
            'tipologia' => $this->getRequest()->getQuery('tipologia'),
            'servizio' => $this->getRequest()->getQuery('servizio'),
            'data' => $this->date_international,
            'ora' => $this->getRequest()->getQuery('ora'),
            'vista' => $this->getRequest()->getQuery('vista'),
            'page' => $this->getRequest()->getQuery('page')
        );        
        $proposte = new Api();
        $proposte->setQueryParams($filtro);
        $proposte->setApi('proposta', function ()
        {
            return array(
                'url' => 'http://api.feelchef.com/proposta//2y14KXIzdfbjQ995fTQb62qC0Ze4vTa1ilkTKcMqJlwM32DtSskeaya',
                'method' => 'GET',
            	'header' => array(
            		'accept'=> 'application/json'),
                'response' => array(
                    'format' => 'json',
                    'valid_codes' => array(
                        '200',
                    	'404'
                    )
                )
            );
        });        
        
        $result = $proposte->proposta();
        $obj = new ArrayObject($result);
        $it = $obj->getIterator();        
        $paginator = new \Zend\Paginator\Paginator(new \Zend\Paginator\Adapter\Null($result['tot_item']));
        $paginator->setItemCountPerPage($result['tot_per_page']);
        $paginator->setPageRange(10);
        $paginator->setCurrentPageNumber($this->getRequest()
            ->getQuery('page', 1));        
        if (! $proposte->isSuccess()) {
            printf("Error (%d): %s\n", $proposte->getStatusCode(), $proposte->getErrorMsg());
        }
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true);
        $htmlViewPart->setTemplate('portale/proposte/proposte_foto');
        if ($this->getRequest()->getQuery('vista') == 2) {
            $htmlViewPart->setTemplate('portale/proposte/proposte_lista');
        }
        $result = array_merge($result, $filtro);        
        $htmlViewPart->setVariables($result);        
        $htmlOutput = $this->getServiceLocator()
            ->get('viewrenderer')
            ->render($htmlViewPart);        
        $jsonModel = new JsonModel();
        if ($result['status'] == 'ok') {
            $jsonModel->setVariables(array(
                'html' => $htmlOutput,
                'status' => $result['status'],
                'filtri' => $filtro,
                'paginator' => $paginator
            ));
        } else {
            $jsonModel->setVariables(array(
                'status' => $result['status'],
                'filtri' => $filtro,
                'paginator' => $paginator
            ));
        }        
        return $jsonModel;
    }

    /**
     * Detail of selected Menù
     */
    public function propostaAction()
    {
    	
        $quickreservationform = $this->getQuickReservationForm();
        $id_proposta = (int) $this->params()->fromRoute('id');
        $filtro = array(        	
            'data' => $this->getRequest()->getQuery('data'),
            'ora' => $this->getRequest()->getQuery('ora'),
            'num_posti' => $this->getRequest()->getQuery('num_posti'),
        );        
        if ($id_proposta == 'undefined') {
            return null;
        } 
        $this->chiamata = 'http://api.feelchef.com/proposta/' . $id_proposta . '/2y14KXIzdfbjQ995fTQb62qC0Ze4vTa1ilkTKcMqJlwM32DtSskeaya';        
        $proposte = new Api();
        $proposte->setApi('proposta', function ($chiamata)
        {
            return array(
                'url' => $chiamata[0],
                'method' => 'GET',
                'response' => array(
                    'format' => 'json',
                    'valid_codes' => array(
                        '200'
                    )
                )
            );
        });        
        $result = $proposte->proposta($this->chiamata);        
        if (! $proposte->isSuccess()) {
            printf("Error (%d): %s\n", $proposte->getStatusCode(), $proposte->getErrorMsg());
        }        
        if ($result['status'] == 'ko') {
            return $this->redirect()->toRoute('home');
        }
        $quickreservationform->get('id')->setValue($id_proposta);
        $quickreservationform->setAttribute('action', '/reservation/' . $id_proposta);
        $quickreservationform->get('reservation_date')->setValue($filtro['data']);
        $quickreservationform->get('reservation_time')->setValue($filtro['ora']);
        $quickreservationform->get('reservation_num_posti')->setValue($filtro['num_posti']);
        return array(
            'proposta' => $result['proposta'],
            'ristorante' => $result['ristorante'],
            'servizi' => $result['servizi'],
            'apertura' => $result['apertura'],
            'altre_proposte' => $result['altre_proposte'],
            'quickreservation_form' => $quickreservationform
        );
    }

    /**
     *
     * @return multitype:string multitype:multitype:string |\Zend\Stdlib\ResponseInterface
     */
    public function imagesAction()
    {
        $proposte = new Api();
        $id = $this->params()->fromRoute('id');        
        $proposte->setApi('proposta', function ($id) use($id)
        {
            return array(
                'url' => 'http://api.feelchef.com/images.image/' . $id . '/2y14KXIzdfbjQ995fTQb62qC0Ze4vTa1ilkTKcMqJlwM32DtSskeaya',
                'method' => 'GET',
                'response' => array(
                    'valid_codes' => array(
                        '200'
                    )
                )
            );
        });
        $result = $proposte->proposta();
        $response = $this->getResponse();
        $response->setContent($result);
        return $response;
    }

    /**
     *
     * @return \Zend\Stdlib\ResponseInterface
     */
    public function checkAction()
    {
        if (! $this->getRequest()->isXmlHttpRequest()) {
            die();
        }
        $jsonModel = new JsonModel();        
        $response = $this->getResponse();
        $response->setStatusCode(200);        
        $id_proposta = (int) $this->params()->fromRoute('id');
        $ora = $this->params()->fromRoute('ora');
        $date = $this->params()->fromRoute('data');
        $this->date_format_international($date);
        $filtro['data'] = (isset($this->date_international)) ? $this->date_international : null;
        $filtro['ora'] = (isset($ora)) ? $ora : null;
        $result = $this->getPropostaTable()->setFiltro($filtro);
        $result = $this->getPropostaTable()->getCheckProposta($id_proposta);        
        $jsonModel->setVariables($result);
        $response->setContent($jsonModel->serialize());
        return $response;
    }

    /**
     *
     * @return object Form
     */
    protected function getSearchForm()
    {
        if (! $this->searchForm) {
            $this->searchForm = $this->getServiceLocator()->get('SearchRequest_form');
        }
        return $this->searchForm;
    }

    /**
     *
     * @return Object Form
     */
    protected function getQuickReservationForm()
    {
        if (! $this->quickreservationform) {
            $this->quickreservationform = $this->getServiceLocator()->get('QuickReservation_form');
        }
        return $this->quickreservationform;
    }

    /**
     *
     * @return object table
     */
    protected function getTipologiaTable()
    {
        if (! $this->tipologiaTable) {
            $this->tipologiaTable = $this->getServiceLocator()->get('TipologiaTable');
        }
        return $this->tipologiaTable;
    }

    /**
     *
     * @return object table
     */
    protected function getServizioTable()
    {
        if (! $this->servizioTable) {
            $this->servizioTable = $this->getServiceLocator()->get('ServizioTable');
        }
        return $this->servizioTable;
    }

    /**
     *
     * @return object table
     */
    protected function getPropostaTable()
    {
        if (! $this->propostaTable) {
            $this->propostaTable = $this->getServiceLocator()->get('PropostaTable');
        }
        return $this->propostaTable;
    }
    
    /**
     * conversion of date for international use
     * @return string 
     */
    protected function date_format_international($data)
    {
    	$data = str_replace('-', '/' , $data);
    	$data = str_replace('.', '/' , $data);
		$locales = array('en' => 'm/d/Y', 'it'=> 'd/m/Y');
		$mysession = new \Zend\Session\Container('base');	
	    $date = date_create_from_format($locales[$mysession->language], $data);
    	$this->date_international = date_format($date, 'Y-m-d');	
    }
    
}
