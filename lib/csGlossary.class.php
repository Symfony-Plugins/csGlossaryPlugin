<?php

/**
* 
*/
class csGlossary
{
	protected $alphabet;
	protected $tables;
	protected $fields;
	protected $glossary;
	protected $text_field_default = 'title';
	
	public function __construct($glossary = 'default')
	{
		$this->alphabet = sfConfig::get('app_glossary_alphabet');
		$this->processConfig($glossary);
		
		$this->glossary = $this->initGlossary();
	}
	
	// ====================
	// = Public functions =
	// ====================
	public function getAlphabet()
	{
		return str_split($this->alphabet);
	}
	public function getGlossaryCollection()
	{
		foreach ($this->tables as $table) 
		{
			$this->addCollectionToGlossary($this->getGlossaryQuery($table)->execute(), $this->fields[$table]);
		}
		
		return $this->glossary;
	}
	public function getForLetter($letter)
	{
		$letter = strtoupper($letter);
		foreach ($this->tables as $table) 
		{
			$this->addCollectionToGlossary($this->getGlossaryQuery($table, $this->fields[$table], $letter)->execute(), $this->fields[$table]);
		}
		return $this->glossary[$letter];
	}
	public function getActiveAlphabet()
	{
		$letters = $this->initGlossary();
		foreach ($this->tables as $table) 
		{
			foreach ($this->getLettersQuery($table, $this->fields[$table])->execute() as $record) 
			{
				$letters[$record->letter] = $record;
			}
		}
		return array_keys(array_filter($letters));
	}
	
	// =====================
	// = Private Functions =
	// =====================
	private function addCollectionToGlossary($collection)
	{
		foreach ($collection as $object) 
		{
			$this->addObjectToGlossary($object);
		}
	}
	private function addObjectToGlossary($object)
	{
		$value = $this->getObjectValue($object);
		$letter = strtoupper($value[0]);
		if($this->hasMultipleTables())
		{
			foreach ($this->glossary[$letter] as $i => $sibling) 
			{
				if(get_class($sibling) != get_class($object))
				{
					$name = $this->getObjectValue($sibling);
					if(strcasecmp($name, $value) > 0)
					{
						$this->glossary[$letter] = $this->insertArrayIndex($this->glossary[$letter], $object, $i);
						return;
					}
				}
			}
		}
		$this->glossary[$letter][] = $object;
	}

	private function getGlossaryQuery($table, $field, $letter = null)
	{
		$q = Doctrine::getTable($table)
						->createQuery()
						->orderBy("$field ASC");
		
		if($letter)
		{
			$q->addWhere("$field LIKE '$letter%'");
		}				
		
		return $q;
	}
	private function getLettersQuery($table, $field)
	{
		$q = Doctrine::getTable($table)
						->createQuery()
						->select("LEFT(UPPER($field), 1) AS letter")
						->orderBy("letter ASC");

		return $q;
	}
	private function getObjectValue($object)
	{
		$field = $this->fields[get_class($object)];
		return $object->$field;
	}
	private function processConfig($glossary)
	{
		foreach(sfConfig::get('app_glossary_'.$glossary) as $key => $value)
		{
			if (is_numeric($key)) 
			{
				$this->tables[] = $value;
				$this->fields[$value] = $this->text_field_default;
			}
			else
			{
				$this->tables[] = $key;
				$this->fields[$key] = $value;
			}
		}
	}
	private function initGlossary()
	{
		$glossary = array();
		foreach ($this->getAlphabet() as $letter) 
		{
			$glossary[$letter] = array();
		}
		return $glossary;
	}
	private function hasMultipleTables()
	{
		return (count($this->tables) > 1);
	}
	private function insertArrayIndex($array, $new_element, $index) 
	{
		$start = array_slice($array, 0, $index); 
		$end = array_slice($array, $index);
		$start[] = $new_element;
		return array_merge($start, $end);
  }
}