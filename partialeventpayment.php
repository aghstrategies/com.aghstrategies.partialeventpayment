<?php

require_once 'partialeventpayment.civix.php';
use CRM_Partialeventpayment_ExtensionUtil as E;


/**
 * hook_civicrm_buildForm($formName, $form)
 */
function partialeventpayment_civicrm_buildForm($formName, $form) {
  // If on the price form option adds an "Installements Field?" checkbox and a "Which full price option does this field refer to?" select
  if ($formName == 'CRM_Price_Form_Option') {
    $form->add('checkbox', 'install_check', ts("Installments Field?"));
    $priceFieldSelect = array();
    $priceID = $form->getVar('_fid');
    try {
      $result = civicrm_api3('PriceFieldValue', 'get', array(
        'sequential' => 1,
        'price_field_id' => $priceID,
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(ts('API Error %1', array(
        'domain' => 'com.aghstrategies.partialeventpayment',
        1 => $error,
      )));
    }

    foreach ($result['values'] as $field) {
      $priceFieldSelect[$field['id']] = $field['label'];
    }
    $form->add('select', "priceFieldSelect", ts('Which full price option does this field refer to?'), $priceFieldSelect);
    $templatePath = realpath(dirname(__FILE__) . "/templates");
    CRM_Core_Region::instance('form-body')->add(array('template' => "{$templatePath}/priceOption.tpl"));
    CRM_Core_Resources::singleton()->addScriptFile('com.aghstrategies.partialeventpayment', 'js/priceOption.js');

    //set defaults
    if ($oid = $form->getVar('_oid')) {
      $sql = $sql = "SELECT * FROM civicrm_installments WHERE pfid = {$oid}";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        $form->getElement('install_check')->setChecked(TRUE);
        $form->getElement('priceFieldSelect')->setValue($dao->reference);
      }
    }
  }
}

/**
 * hook_civicrm_postProcess($formName, $form)
 */
function partialeventpayment_civicrm_postProcess($formName, $form) {
  if ($formName == "CRM_Price_Form_Option") {
    $submit = $form->_submitValues;

    // Delete old entry
    if (!array_key_exists('install_check', $submit) && $pfid = $form->getVar('_oid')) {
      $sql = "SELECT pfid FROM civicrm_installments WHERE pfid = {$pfid}";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        $deleteSQL = "DELETE FROM civicrm_installments WHERE pfid = {$pfid}";
        CRM_Core_DAO::executeQuery($deleteSQL);
      }
    }

    //Update/Insert new entry
    if (array_key_exists('install_check', $submit) && array_key_exists('priceFieldSelect', $submit) && $pfid = $form->getVar('_oid')) {
      $ref = $submit['priceFieldSelect'];
      $sql = "SELECT pfid FROM civicrm_installments WHERE pfid = {$pfid}";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        $updateSQL = "UPDATE civicrm_installments SET reference = {$ref} WHERE pfid = {$pfid}";
        CRM_Core_DAO::executeQuery($updateSQL);
      }
      else {
        $insertSQL = "INSERT INTO civicrm_installments (pfid, reference) VALUES ({$pfid}, {$ref})";
        CRM_Core_DAO::executeQuery($insertSQL);
      }
    }
  }
  //end price option form

  if ($formName == "CRM_Event_Form_Registration_Confirm") {
    // get id for partially paid participant status
    try {
      $partiallyPaidPartStatus = civicrm_api3('ParticipantStatusType', 'getsingle', [
        'return' => ["id"],
        'label' => "Partially Paid",
      ]);
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(ts('API Error %1', array(
        'domain' => 'com.aghstrategies.partialeventpayment',
        1 => $error,
      )));
    }

    // get id for partially paid contribution status
    try {
      $partiallyPaidContribStatus = civicrm_api3('OptionGroup', 'getsingle', [
        'name' => "contribution_status",
        'api.OptionValue.getsingle' => ['option_group_id' => "\$value.id", 'name' => "Partially paid"],
      ]);
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Error::debug_log_message(ts('API Error %1', array(
        'domain' => 'com.aghstrategies.partialeventpayment',
        1 => $error,
      )));
    }
    $partiallyPaidContribStatus = $partiallyPaidContribStatus['api.OptionValue.getsingle']['value'];

    $params = $form->getVar('_params');
    $lineItem = $form->getVar('_lineItem');
    $pfidArray = array();
    foreach ($lineItem as $key => $value) {
      foreach ($value as $k => $v) {
        $pfid = $k;
        $sql = "SELECT * FROM civicrm_installments WHERE pfid = {$pfid}";
        $dao = CRM_Core_DAO::executeQuery($sql);
        if ($dao->fetch()) {
          if ($refID = $dao->reference) {
            $participantID = $form->getVar('_participantId');
            //get referenced price field Info
            try {
              $refInfo = civicrm_api3('PriceFieldValue', 'getsingle', array(
                'sequential' => 1,
                'id' => $refID,
              ));
            }
            catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                'domain' => 'com.aghstrategies.partialeventpayment',
                1 => $error,
              )));
            }
            //get current pfid info
            try {
              $pfidInfo = civicrm_api3('PriceFieldValue', 'getsingle', array(
                'sequential' => 1,
                'id' => $pfid,
              ));
            }
            catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                'domain' => 'com.aghstrategies.partialeventpayment',
                1 => $error,
              )));
            }

            //get contribution info
            try {
              $contribution = civicrm_api3('Contribution', 'getsingle', array(
                'sequential' => 1,
                'id' => $params['contributionID'],
              ));
            }
            catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                'domain' => 'com.aghstrategies.partialeventpayment',
                1 => $error,
              )));
            }

            //get all lineitems for this participant
            try {
              $lineItems = civicrm_api3('LineItem', 'get', array(
                'sequential' => 1,
                'entity_id' => $participantID,
                'entity_table' => 'civicrm_participant',
              ));
            }
            catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                'domain' => 'com.aghstrategies.partialeventpayment',
                1 => $error,
              )));
            }
            // loop through all line items and only fix those who have installment price field
            $newTotal = 0;
            foreach ($lineItems['values'] as $lineItem) {
              if ($lineItem['price_field_value_id'] == $pfid) {
                $pfidArray[] = $pfid;
                /*1. change the civicrm_line_item row to have*/
                $id = $lineItem['id'];
                $unit_price = number_format(($refInfo['amount']), 2);
                $total = $lineItem['qty'] * $refInfo['amount'];
                $totalFormatted = number_format($total, 2);
                $lineParams = array(
                  'sequential' => 1,
                  'entity_id' => $participantID,
                  'id' => $id,
                  'label' => $refInfo['label'],
                  'unit_price' => $unit_price,
                  'price_field_value_id' => $refInfo['id'],
                  'line_total' => $totalFormatted,
                  'qty' => $lineItem['qty'],
                  'price_field_id' => $lineItem['price_field_id'],
                );
                try {
                  $updateLineItem = civicrm_api3('LineItem', 'create', $lineParams);
                }
                catch (CiviCRM_API3_Exception $e) {
                  $error = $e->getMessage();
                  CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                    'domain' => 'com.aghstrategies.partialeventpayment',
                    1 => $error,
                  )));
                }
                /*2. change the civicrm_financial_item row to */
                $refLabel = $refInfo['label'];
                $refAmount = $refInfo['amount'];

                //TODO get this thru the API in case it varies system to system
                $paymentStatusId = 2;

                $sql = "UPDATE civicrm_financial_item SET description = '{$refLabel}', amount = {$refAmount}, status_id = {$paymentStatusId} WHERE entity_id = {$id}";
                $dao = CRM_Core_DAO::executeQuery($sql);
                //update total
                $newTotal = $newTotal + $total;
              }
              else {
                $newTotal = $newTotal + $lineItem['line_total'];
              }
            }
            //end of inner lineitem foreach

            /* 3. change the civicrm_financial_trxn row to have from_financial_account_id = the A/R account*/
            try {
              $accounts = civicrm_api3('FinancialAccount', 'getsingle', array('sequential' => 1, 'name' => "Accounts Receivable"));
            }
            catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                'domain' => 'com.aghstrategies.partialeventpayment',
                1 => $error,
              )));
            }
            try {
              $bankingFees = civicrm_api3('FinancialAccount', 'getsingle', array('sequential' => 1, 'name' => "Banking Fees"));
            }
            catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                'domain' => 'com.aghstrategies.partialeventpayment',
                1 => $error,
              )));
            }
            $ar = $accounts['id'];
            //$newTotal = number_format($newTotal, 2);
            $trxn = $contribution['trxn_id'];
            if ($trxn && $ar && in_array($v['price_field_value_id'], $pfidArray)) {
              $checkSQL = "SELECT trxn_date, payment_instrument_id, currency, status_id FROM civicrm_financial_trxn WHERE trxn_id = '{$trxn}'";
              $checkDAO = CRM_Core_DAO::executeQuery($checkSQL);
              if ($checkDAO->fetch()) {
                $date = $checkDAO->trxn_date;
                $updateSQL = "UPDATE civicrm_financial_trxn SET from_financial_account_id = {$ar}, to_financial_account_id = {$bankingFees['id']} WHERE trxn_id = '{$trxn}' AND total_amount <> 1.50";
                $updateDAO = CRM_Core_DAO::executeQuery($updateSQL);
                $dealWithTestFee = "UPDATE civicrm_financial_trxn SET from_financial_account_id = {$ar}, to_financial_account_id = {$ar} WHERE trxn_id = '{$trxn}' AND total_amount = 1.50";
                $testFee = CRM_Core_DAO::executeQuery($dealWithTestFee);
                if ($updateDAO->affectedRows()) {
                  $insertSQL = "
						  	    INSERT into civicrm_financial_trxn (trxn_date, total_amount, currency, from_financial_account_id, to_financial_account_id, status_id, payment_instrument_id)
						  	    VALUES ('{$date}', {$newTotal}, '{$checkDAO->currency}', null, {$ar}, {$checkDAO->status_id}, {$checkDAO->payment_instrument_id})";
                  CRM_Core_DAO::executeQuery($insertSQL);
                } //end update fetch
              }//end check fetch
            }//end if txrn_exists

            /*4. change the civicrm_contribution row to have*/
            $levelSQL = "SELECT amount_level FROM civicrm_contribution WHERE id = {$contribution['id']}";
            $levelCheck = CRM_Core_DAO::executeQuery($levelSQL);
            if ($levelCheck->fetch()) {
              $oldLevel = $levelCheck->amount_level;
              $newLevel = str_replace($pfidInfo['label'], $refInfo['label'], $oldLevel);
              $sql = "
							  UPDATE civicrm_contribution
							  SET contribution_status_id = $partiallyPaidContribStatus,
							    receive_date = CURDATE(),
							    amount_level = '{$newLevel}',
                  net_amount = $newTotal,
                  fee_amount = 0,
							    total_amount = $newTotal
						    WHERE id = {$contribution['id']} ";
              CRM_Core_DAO::executeQuery($sql);
            }
            /*5.change the civicrm_participant row to have status_id = the ID for partially paid*/
            $participantParams = array(
              'sequential' => '1',
              'id' => $participantID,
              "status_id" => $partiallyPaidPartStatus['id'],
              "participant_fee_amount" => $newTotal,
            );
            try {
              $updateParticipant = civicrm_api3('Participant', 'create', $participantParams);
            }
            catch (CiviCRM_API3_Exception $e) {
              $error = $e->getMessage();
              CRM_Core_Error::debug_log_message(ts('API Error %1', array(
                'domain' => 'com.aghstrategies.partialeventpayment',
                1 => $error,
              )));
            }
          } //end of if reference exists
        } //end of DAO fetch
      } //end of K and V foreach
    } // end of lineItem foreach
  } // end confirm postproccess
} //end of function

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function partialeventpayment_civicrm_config(&$config) {
  _partialeventpayment_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function partialeventpayment_civicrm_xmlMenu(&$files) {
  _partialeventpayment_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function partialeventpayment_civicrm_install() {
  /* Create CiviCRM installments table */
  CRM_Core_DAO::executeQuery('
  CREATE TABLE IF NOT EXISTS civicrm_installments (
    pfid int(11) unsigned NULL COMMENT "",
    reference int(11) unsigned NULL COMMENT ""
  );');
  _partialeventpayment_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function partialeventpayment_civicrm_postInstall() {
  _partialeventpayment_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function partialeventpayment_civicrm_uninstall() {
  _partialeventpayment_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function partialeventpayment_civicrm_enable() {
  _partialeventpayment_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function partialeventpayment_civicrm_disable() {
  _partialeventpayment_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function partialeventpayment_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _partialeventpayment_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function partialeventpayment_civicrm_managed(&$entities) {
  _partialeventpayment_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function partialeventpayment_civicrm_caseTypes(&$caseTypes) {
  _partialeventpayment_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function partialeventpayment_civicrm_angularModules(&$angularModules) {
  _partialeventpayment_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function partialeventpayment_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _partialeventpayment_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function partialeventpayment_civicrm_entityTypes(&$entityTypes) {
  _partialeventpayment_civix_civicrm_entityTypes($entityTypes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function partialeventpayment_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function partialeventpayment_civicrm_navigationMenu(&$menu) {
  _partialeventpayment_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _partialeventpayment_civix_navigationMenu($menu);
} // */
