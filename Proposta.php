<?php
namespace Portale\Model;

use Zend\Filter\Boolean;
use Zend\Validator\Explode;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class Proposta extends Sql
{
	public $filtro;
	protected $adapter;
 	protected $serviceLocator;
	protected $sql;
	
	public function __construct(Sql $sql)
	{
		$this->sql = $sql;
		$this->adapter = $this->sql->adapter;
	}
	
	public function setFiltro($filtro)
	{
		
		$this->filtro = $filtro;
	}
	
	public function getFiltro()
	{
		return $this->filtro;
	}
	

	public function getCheckProposta($id_proposta)
	{
		$select = $this->sql->select();
		$select->columns(array('ristorante_id',
							   'id_proposta',
							   'data_da',
							   'data_a', 
        					   'titolo_proposta', 
        					   'prezzo',
							   'ristorante_id'));        
		$select->from(array('a' => 'proposta'));
		$select->join(array('b' => 'attivita'),'a.ristorante_id = b.ristorante_id', array());
		$select->where->equalTo('a.id_proposta',$id_proposta);	

		
		if ($this->filtro['data'])
		{
			$select->join(array('d' => 'Orario'), 'a.ristorante_id = d.ristorante_id');
			$select->where(array('weekday("'.$this->filtro['data'].'")+1 = d.giorno'));				
			$select->where(array( '"'.$this->filtro['data'].'"' . ' between a.data_da and a.data_a'));
		}
		
		if ($this->filtro['ora'])
		{										
			$select->where(array( '(("'.$this->filtro['ora'].'"' . ' between d.apertura_t1 and d.chiusura_t1) or ("'.$this->filtro['ora'].'"' . ' between d.apertura_t2 and d.chiusura_t2))'));
		}		

		//preparo lo statment
		$statement = $this->sql->prepareStatementForSqlObject($select);
		//print_r($this->sql->getSqlStringForSqlObject($select));
		//exit;
		
		// eseguo la query
		$rowset = $statement->execute();
		$row = $rowset->count();
        if ($row == 0) {        	
            return array(
                	'status' => 'ko',            		            		       		                	
			);			
        };
		        
		 		
		return  array(
                	'status' => 'ok',
					'proposta' => $rowset->current()			 
		);		
	}	
}