<?php

class RowController extends Controller
{
	public $schema;
	public $table;

	/**
	 * @var Default layout for this controller
	 */
	public $layout = 'schema';

	public function __construct($id, $module=null)
	{
		if(Yii::app()->request->isAjaxRequest)
			$this->layout = false;

		$request = Yii::app()->getRequest();
		$this->schema = $request->getParam('schema');
		$this->table = $request->getParam('table');

		parent::__construct($id, $module);
		$this->connectDb($this->schema);
	}

	public function actionUpdate()
	{

		$db = $this->db;

		$pk = CPropertyValue::ensureArray($db->getSchema()->getTable($this->table)->primaryKey);
		$column = Yii::app()->getRequest()->getParam('column');
		$newValue = json_decode(Yii::app()->getRequest()->getParam('value'), true);
		$null = Yii::app()->getRequest()->getParam('isNull');
		$attributes = json_decode(Yii::app()->getRequest()->getParam('attributes'), true);

		// SET datatype
		if(is_array($newValue))
		{
			$newValue = implode(',', $newValue);
		}

		$attributesCount = count($pk);

		if($null)
		{
			$newValue = null;
		}

		$response = new AjaxResponse();

		Row::$db = $db;

		if(count($attributes) == 1)
		{
			$findAttributes = $attributes[$column];
		}
		else
		{
			$findAttributes = $attributes;
		}

		$row = Row::model()->findByPk($findAttributes);

		try {

			$row->setAttribute($column, $newValue);
			$sql = $row->save();

			$response->addData(null, array(
				'value' => ($null ? 'NULL' : htmlspecialchars($row->getAttribute($column))),
				'column' => $column,
				'isPrimary' => in_array($column, $pk),
				'isNull' => $null,
				'visibleValue' => ($null ? '<span class="null">NULL</span>' : htmlspecialchars($row->getAttribute($column)))
			));

			// Refresh the page if the row could not be found in database anymore
			if(!$row->refresh() || $row->getAttribute($column) != $newValue) {
				$response->refresh = true;

				// @todo (rponudic) check if a notification is necessary in this case
				//$response->addNotification('warning', 'type does not match');
			}

			$response->addNotification('success', Yii::t('message', 'successUpdateRow'), null, $sql);

		}
		catch (DbException $ex)
		{
			$response->addNotification('error', Yii::t('message', 'errorUpdateRow'), $ex->getText(), $sql);
			$response->addData(null, array('error'=>true));
		}

		$response->send();

	}

	public function actionDelete()
	{

		$response = new AjaxResponse();

		$data = json_decode($_POST['data'], true);

		try
		{

			foreach($data AS $attributes)
			{
				$row = new Row;
				$row->attributes = $attributes;

				$pkAttributes = $row->getPrimaryKey();
				$row->attributes = null;

				$row->attributes = $pkAttributes;

				$sql .= $row->delete() . "\n\n";
			}

		}
		catch (DbException $ex)
		{
			$response->addNotification('error', Yii::t('message', 'errorDeleteRow'), $ex->getText(), $sql, array('isSticky'=>true));
		}

		$response->refresh = true;
		$response->addNotification('success', Yii::t('message', 'successDeleteRows', array(count($data), '{rowCount}' => count($data))), null, $sql);

		$response->send();
	}

	public function actionInput()
	{
		$attributes = json_decode(Yii::app()->getRequest()->getParam('attributes'), true);
		$column = Yii::app()->getRequest()->getParam('column');
		$oldValue = Yii::app()->getRequest()->getParam('oldValue');
		$rowIndex = Yii::app()->getRequest()->getParam('rowIndex');

		// Single PK
		$kvAttributes = $attributes;

		if(count($attributes) == 1)
		{
			$attributes = array_pop($attributes);
		}

		$row = Row::model()->findByPk($attributes);
		$column = $this->db->getSchema()->getTable($this->table)->getColumn($column);

		$this->render('input', array(
			'column' => $column,
			'row' => $row,
			'attributes' => $kvAttributes,
			'oldValue' => str_replace("\n", "", $oldValue),				// @todo (rponudic) double-check if this is the solution!?
			'rowIndex' => $rowIndex,
		));

	}

	public function actionExport()
	{


	}

}