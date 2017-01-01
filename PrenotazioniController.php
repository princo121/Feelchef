<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace UserAdmin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Stdlib\Parameters;
use Zend\Form\Form;
use Zend\Form\Element;
use ZendService\Api\Api;
use Zend\View\Model\JsonModel;
use Zend\Http\PhpEnvironment\Request;
use Zend\Math\Rand;
use ArrayObject;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Db\Sql\Ddl\Column\Boolean;

class PrenotazioniController extends AbstractActionController implements EventManagerAwareInterface
{

    /**
     *
     * @var Form
     */
    protected $reservationForm;

    /**
     *
     * @var proposta table
     */
    protected $propostaTable;

    /**
     *
     * @var prenotazioneTable
     */
    protected $prenotazioneTable;

    /**
     *
     * @var user
     */
    protected $user;

    /**
     *
     * @var reservation_date
     */
    protected $reservation_date;

    /**
     *
     * @var reservation_time
     */
    protected $reservation_time;

    /**
     *
     * @var message
     */
    protected $message;

    /**
     *
     * @var form annullamento prenotazione
     */
    protected $annullaprenotazioneForm;

    /**
     *
     * @var prenotazione
     */
    protected $prenotazione;

    /**
     *
     * @var MailService servizio mail
     */
    protected $MailService;

    /**
     *
     * @var prenotazione_data
     */
    protected $prenotazione_data;

    /**
     *
     * @var ModelMail table
     */
    protected $ModelMail;
    
    /**
     * 
     * @var string id_prenotazione
     */
    protected $id_prenotazione;

    /**
     *
     * @var string date_international
     */
    protected $date_international;
    
    /**
     * 
     * @var string Codice Coupon
     */
    protected $cod_coupon;
    
    /**
     * 
     * @var ModelCoupon table
     */
    protected $ModelCoupon;
    
    /**
     * Pagina principale
     * Elenco prenotazioni e form di cancellazione della prenotazione
     */
    public function indexAction()
    {
        $form = $this->getAnnullaprenotazioneForm();
        $request = $this->getRequest();
        $inputfilter = $this->ModelPrenotazione()->getInputFilter();
        $status = null;
        if ($request->isPost()) {
            $form->setInputFilter($inputfilter);
            $form->setData($request->getPost());
            if ($form->isValid()) {
                $this->ModelPrenotazione()->setUserId($this->zfcUserAuthentication()->getIdentity()->getId());
                $data_form = $form->getData();
                $this->ModelPrenotazione()->SetIdPrenotazione((int) $data_form['id_prenotazione']);
                $this->id_prenotazione = $data_form['id_prenotazione'];
                $this->prenotazione = $this->ModelPrenotazione()->getPrenotazioneByID();
                $this->prenotazione['motivo_annullamento'] = $data_form['motivo'];
                $this->prenotazione['annullata'] = 'C';
                $this->prenotazione['data_annullata'] = date("Y-m-d");
                $this->ModelPrenotazione()->exchangeArray($this->prenotazione);
                $this->ModelPrenotazione()->savePrenotazione();
                $status = 'Annullamento Prenotazione Effettuato';
                
                // La mail viene salvata e inviata tramite un cron job
                $this->SalvaMail('Annulla');
                                
                return $this->redirect()->toRoute('prenotazioni');
            } else {
                $status = false;
            }
        }
        
        return array(
            'status' => $status,
            'form' => $form
        );
    }

    /**
     *
     * @return JSON Lista delle prenotazioni
     */
    public function prenotazioniAction()
    {
        if (! $this->getRequest()->isXmlHttpRequest()) {
            return array();
        }
        
        $last_id = $this->params()->fromRoute('id');
        if ($last_id == 'undefined') {
            $last_id = null;
        };
        $user_id = $this->zfcUserAuthentication()->getIdentity()->getId();
        $prenotazioni = $this->ModelPrenotazione()->getprenotazioni($user_id, $last_id);
        
        $htmlViewPart = new ViewModel();
        $htmlViewPart->setTerminal(true)
            ->setTemplate('user-admin/prenotazioni/prenotazioni')
            ->setVariables($prenotazioni);
        
        $htmlOutput = $this->getServiceLocator()
            ->get('viewrenderer')
            ->render($htmlViewPart);
        
        $jsonModel = new JsonModel();
        if ($prenotazioni['status'] == 'ok') {
            $jsonModel->setVariables(array(
                'html' => $htmlOutput,
                'lastid' => $prenotazioni['last_id'],
                'status' => $prenotazioni['status']
            ));
        } else {
            $jsonModel->setVariables(array(
                'status' => $prenotazioni['status']
            ));
        }
        
        return $jsonModel;
    }

    /**
     *
     * Pagina di conferma per la prenotazione
     *
     * @return form
     */
    public function newAction()
    {
        $form = $this->getReservationForm();
        $id_proposta = (int) $this->params()->fromRoute('id');
        $form->setAttribute('action', '/prenotazione/prenota/' . $id_proposta);
        $this->reservation_date = $this->getRequest()->getQuery('reservation_date');
        $this->reservation_time = $this->getRequest()->getQuery('reservation_time');
        $check = $this->checkAction();
        
        if ($id_proposta == 'undefined') {
            return $this->redirect()->toRoute('home');
        }
        ;
        
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
        
        $reservation = array(
            'reservation_date' => $this->getRequest()->getQuery('reservation_date'),
            'reservation_time' => $this->getRequest()->getQuery('reservation_time'),
            'reservation_num_posti' => $this->getRequest()->getQuery('reservation_num_posti'),
            'reservation_num_menu' => $this->getRequest()->getQuery('reservation_num_menu'),
            'nominativo' => $this->zfcUserAuthentication()
                ->getIdentity()
                ->getDisplayname(),
            'email' => $this->zfcUserAuthentication()
                ->getIdentity()
                ->getEmail(),
            'check' => $check['status']
        );
        
        $user_profile = $this->ModelProfile()->getPofileData($this->zfcUserAuthentication()
            ->getIdentity()
            ->getId());
        foreach ($user_profile as $key => $value) {
            if ($value->key == 'tel') {
                $reservation['telefono'] = $value->value;
            }
        }
        $form->get('reservation_date')->setValue($this->getRequest()
            ->getQuery('reservation_date'));
        $form->get('reservation_time')->setValue($this->getRequest()
            ->getQuery('reservation_time'));
        $form->get('reservation_num_menu')->setValue($this->getRequest()
            ->getQuery('reservation_num_menu'));
        $form->get('reservation_num_posti')->setValue($this->getRequest()
            ->getQuery('reservation_num_posti'));
        
        return array(
            'form' => $form,
            'proposta' => $result['proposta'],
            'reservation' => $reservation,
        	'flashMessages' => $this->flashMessenger()->getMessages()
        );
    }

    /**
     *
     * @return avoid Esegue la prenotazione
     */
    public function prenotaAction()
    {
    	$translate = $this->getServiceLocator()->get('viewhelpermanager')->get('translate');
        $id_proposta = (int) $this->params()->fromRoute('id');
        $this->reservation_date = $this->getRequest()->getPost('reservation_date');
        $this->reservation_time = $this->getRequest()->getPost('reservation_time');
        $check = $this->checkAction();
        
        if (! $check['status'] == 'ok') {
            // modificare con
            // settare un template di errore
            // array con messaggio errore e return
            return $this->redirect()->toRoute('home');
        }
        $request = $this->getRequest();
        if ($request->isPost()) {
            $form = $this->getReservationForm();
            $form->setData($request->getPost());
            if ($form->isValid()) {
            	// Effettuo il controllo del codice coupon se questo è presente
            	if ($this->getRequest()->getPost('cod_coupon')) {            		
            		if (!$this->check_coupon()){            			
            			$this->flashMessenger()->addMessage($translate('Coupon Non Valido'));
            			$posti = '&reservation_num_posti='.$this->getRequest()->getPost('reservation_num_posti');
            			$resdata = '&reservation_date='.$this->reservation_date;
            			$resmenu = '&reservation_num_menu='.$this->getRequest()->getPost('reservation_num_menu');
            			$restime = '&reservation_time='.$this->reservation_time;       
            			$uni = $restime.$resmenu.$resdata.$posti;      			
            			return $this->redirect()->toUrl('/reservation/' . $id_proposta .'?id='.$id_proposta.$uni);
            		}            		
            	}
            	
                $cod_prenotazione = Rand::getString(6, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', true);
                $parts = explode('-', $this->reservation_date);
                
                $this->prenotazione_data = array(
                    'id_proposta' => $id_proposta,
                    'ristorante_id' => $check['proposta']['ristorante_id'],
                    'user_id' => $this->zfcUserAuthentication()->getIdentity()->getId(),
                    'data' => $this->date_international,
                    'persone' => $this->getRequest()->getPost('reservation_num_posti'),
                    'num_menu' => $this->getRequest()->getPost('reservation_num_menu'),
                    'ora' => $this->reservation_time,
                    'cod_prenotazione' => $cod_prenotazione,
                    'note' => $this->getRequest()->getPost('reservation_body'),
                	'cod_coupon' => $this->getRequest()->getPost('cod_coupon')
                );
                
                // Eseguo il salvataggio del telefono qualora non fosse gia presente
                if ($this->getRequest()->getPost('telefono')) {
                    $data = array(
                        'key' => 'tel',
                        'value' => $this->getRequest()->getPost('telefono'),
                        'user_id' => $this->zfcUserAuthentication()->getIdentity()->getId()
                    );
                    $this->ModelProfile()->setPofileData($data);
                    $this->ModelProfile()->saveProfilo();
                }
                
                // Salvo la prenotazione
                $this->ModelPrenotazione()->setUserId($this->zfcUserAuthentication()->getIdentity()->getId());
                $this->ModelPrenotazione()->setPrenotazioneData($this->prenotazione_data);
                $this->id_prenotazione = $this->ModelPrenotazione()->savePrenotazione();
                
                // Salvo la Mail che viene inviata all'utente e ristoratore e che verrà inviata tramite un cron job                
                $this->SalvaMail('Prenotazione');
                
                // $mailService = $this->MailService();
                // $message = $mailService->createMessageFromHTML('no-replay@feelchef.com', 'valmirko@tiscali.it', 'prova', 'prova testo');
                // $message->setBcc('valmirko@alice.it');
                // $mailService->send($message);
            } else {
                $this->message = 'Errore nella prenotazione - Contattare Amministratore';
                return $this->redirect()->toRoute('reservation/' . $id_proposta);
            }
            $this->flashMessenger()->addMessage(array(
                'nominativo' => $this->zfcUserAuthentication()->getIdentity()->getDisplayname(),
                'codprenotazione' => $cod_prenotazione
            ));
            return $this->redirect()->toRoute('reservation-success');
        }
    }

    /**
     *
     * @return multitype:multitype: |Ambigous <\Zend\Http\Response, \Zend\Stdlib\ResponseInterface>
     *         Messaggio di successo dell'avvenuta prenotazione
     */
    public function successAction()
    {
        $return = array(
            'success' => true
        );
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            $return = $flashMessenger->getMessages();
            return array(
                'messaggi' => $return
            );
        }
        return $this->redirect()->toRoute('home');
    }

    private function checkAction()
    {
        $id_proposta = (int) $this->params()->fromRoute('id');
        $ora = $this->reservation_time;
        $this->date_format_international($this->reservation_date);
        $date = $this->date_international;
        $filtro['data'] = (isset($date)) ? $date : null;
        $filtro['ora'] = (isset($ora)) ? $ora : null;
        $result = $this->getPropostaTable()->setFiltro($filtro);
        $result = $this->getPropostaTable()->getCheckProposta($id_proposta);
        return $result;
    }
    
    /**
     * Check Codice Coupon
     * @return Boolean
     */
    private function check_coupon ()
    {    	
    	$cod_coupon = $this->getRequest()->getPost('cod_coupon');
    	return $this->ModelCoupon()->getCoupon($cod_coupon);
    }

    /**
     * Salva la mail della prenotazione o dell'annullamento di una prenotazione
     * Sia per il Ristoratore che per l'utente
     */
    protected function SalvaMail($tipo)
    {
        $renderer = $this->getServiceLocator()->get('ViewRenderer');
        $this->ModelPrenotazione()->SetUserId($this->zfcUserAuthentication()->getIdentity()->getId());
        $this->ModelPrenotazione()->SetIdPrenotazione($this->id_prenotazione);
        $dati_prenotazione = $this->ModelPrenotazione()->getAllPrenotazioneById();
        
        if ($tipo == 'Prenotazione') {
                                    
            // Mail dell'utente
            $content = $renderer->render('email/email_prenotazione_user', $dati_prenotazione);
            
            $data = array(
                'mail_from' => 'no-replay@feelchef.com',
                'mail_to' => $this->zfcUserAuthentication()->getIdentity()->getEmail(),
                'subject' => 'Prenotazione FeelChef',
                'body' => $content,
                'user_id' => $this->zfcUserAuthentication()->getIdentity()->getId()
            );
            
            $this->Modelmail()->exchangeArray($data);
            $this->Modelmail()->save();
            
            // Mail Ristoratore            
            $content = $renderer->render('email/email_prenotazione_ristoratore', $dati_prenotazione); 
            $data = array( 'mail_from' => 'no-replay@feelchef.com', 
                            'mail_to' =>  $dati_prenotazione['prenotazione']['email_ristoratore'],
                            'subject' => 'Prenotazione FeelChef', 
                            'body' => $content,                              
                            'ristorante_id' => $dati_prenotazione['prenotazione']['ristorante_id'] 
                            ); 
            $this->Modelmail()->exchangeArray($data); 
            $this->Modelmail()->save();
             
        }
        
        if ($tipo == 'Annulla') {
            // Mail dell'utente
            $content = $renderer->render('email/email_annulla_user', $dati_prenotazione);
            
            $data = array(
                'mail_from' => 'no-replay@feelchef.com',
                'mail_to' => $this->zfcUserAuthentication()->getIdentity()->getEmail(),
                'subject' => 'Annulla prenotazione FeelChef',
                'body' => $content,
                'user_id' => $this->zfcUserAuthentication()->getIdentity()->getId()
            );
            
            $this->Modelmail()->exchangeArray($data);
            $this->Modelmail()->save();
            
            // Mail Ristoratore
            $content = $renderer->render('email/email_annulla_ristoratore', $dati_prenotazione);
            $data = array( 'mail_from' => 'no-replay@feelchef.com',
                'mail_to' =>  $dati_prenotazione['prenotazione']['email_ristoratore'],
                'subject' => 'Cancellazione prenotazione FeelChef',
                'body' => $content,
                'ristorante_id' => $dati_prenotazione['prenotazione']['ristorante_id']
            );
            $this->Modelmail()->exchangeArray($data);
            $this->Modelmail()->save();
        }
    }

    /**
     *
     * @return \Zend\Form\Form
     */
    protected function getReservationForm()
    {
        if (! $this->reservationForm) {
            $this->reservationForm = $this->getServiceLocator()->get('Reservation_form');
        }
        return $this->reservationForm;
    }

    /**
     *
     * @return Proposta Table
     */
    protected function getPropostaTable()
    {
        if (! $this->propostaTable) {
            $this->propostaTable = $this->getServiceLocator()->get('PropostaTable');
        }
        return $this->propostaTable;
    }

    /**
     *
     * @return Prenoyazione Table
     */
    protected function ModelPrenotazione()
    {
        if (! $this->prenotazioneTable) {
            $sm = $this->getServiceLocator();
            $this->prenotazioneTable = $sm->get('PrenotazioneTable');
        }
        return $this->prenotazioneTable;
    }

    /**
     *
     * @return User Table
     */
    protected function ModelProfile()
    {
        if (! $this->user) {
            $sm = $this->getServiceLocator();
            $this->user = $sm->get('User');
        }
        return $this->user;
    }

    /**
     *
     * @return Form
     */
    protected function getAnnullaprenotazioneForm()
    {
        if (! $this->annullaprenotazioneForm) {
            $this->annullaprenotazioneForm = ($this->getServiceLocator()->get('Annullaprenotazione_form'));
        }
        return $this->annullaprenotazioneForm;
    }

    /**
     *
     * @return Mail Table
     */
    protected function ModelMail()
    {
        if (! $this->ModelMail) {
            $sm = $this->getServiceLocator();
            $this->ModelMail = $sm->get('MailTable');
        }
        return $this->ModelMail;
    }
    
    /**
     *
     * @return Coupon Table
     */
    protected function ModelCoupon()
    {
    	if (! $this->ModelCoupon) {
    		$sm = $this->getServiceLocator();
    		$this->ModelCoupon = $sm->get('CouponTable');
    	}
    	return $this->ModelCoupon;
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
