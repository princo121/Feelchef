<?php

namespace UserAdmin\Form;

use Zend\Form\Form;
use Zend\Form\Element;

class ReservationForm extends Form
{
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct('reservation');
		$this->setAttribute('method', 'post');

		$this->add(array(
            'name' => 'reservation_date',
			'type'  => 'Zend\Form\Element\Hidden',
			'placeholder' => 'Data',
			'attributes' => array(
				'required' => 'required'
			)
		));

		$this->add(array(
             'name' => 'reservation_time',
		 	 'type'  => 'Zend\Form\Element\Hidden',
		     'attributes' => array(
				'required' => 'required',
			)
		));		
		

		$this->add(array(
    		'type'  => 'Zend\Form\Element\Hidden',	
		   	'name' => 'reservation_num_menu',
            'attributes' => array(
				'required' => 'required',
			),	
		));       
		
		$this->add(array(
    		'type' => 'Zend\Form\Element\Hidden',
		   	'name' => 'reservation_num_posti',
            'attributes' => array(
				'required' => 'required',
			),		
		));       
		
        $this->add(array(
        	'type' => 'Zend\Form\Element\Checkbox',
            'name' => 'check_accetto',
        	'attributes' => array(
				'required' => 'required',
			),
            'options' => array(
                'label' => 'Accetto',
         		'checked_value' => 'S', 
		        'unchecked_value' => 'N',
            ),
        ));  		
		
		$this->add(array(
             'name' => 'reservation_body',
             'attributes' => array(
             'type'  => 'textarea',
            ),
            'options' => array(
                'label' => 'Richieste Speciali',
            ),
        ));
            
	    $this->add(array(
		     'type' => 'Zend\Form\Element\Csrf',
		     'name' => 'csrf',
		     'options' => array(
		             'csrf_options' => array(
		                     'timeout' => 600
		             )
		     )
		));          
                      
		$this->add(array(
            'name' => 'prenota',
            'attributes' => array(
                'type'  => 'submit',
                'value' => 'CONFERMA',
                'id' => 'submitbutton',
        		'class' => 'btn-large btn-success'
		),
		));
	}
}
