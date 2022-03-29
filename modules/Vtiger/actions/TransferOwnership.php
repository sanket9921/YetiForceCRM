<?php
/**
 * Transfer ownership modal action file.
 *
 * @package   Action
 *
 * @copyright YetiForce S.A.
 * @license   YetiForce Public License 5.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

/**
 * Transfer ownership modal action class.
 */
class Vtiger_TransferOwnership_Action extends \App\Controller\Action
{
	/** {@inheritdoc} */
	public function checkPermission(App\Request $request)
	{
		$moduleName = $request->getModule();
		$currentUserPrivilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		$sourceView = $request->getByType('sourceView');
		if (
			$currentUserPrivilegesModel->hasModuleActionPermission($moduleName, 'EditView')
			&& ('Detail' !== $sourceView && 'List' !== $sourceView)
			|| ('List' === $sourceView && !$currentUserPrivilegesModel->hasModuleActionPermission($moduleName, 'MassTransferOwnership'))
			|| ('Detail' === $sourceView && !$currentUserPrivilegesModel->hasModuleActionPermission($moduleName, 'DetailTransferOwnership'))
		) {
			throw new \App\Exceptions\NoPermitted('LBL_PERMISSION_DENIED', 406);
		}
	}

	/** {@inheritdoc} */
	public function process(App\Request $request)
	{
		$moduleName = $request->getModule();
		$transferOwnerId = $request->getInteger('transferOwnerId');
		$relatedModules = $request->getByType('related_modules', 'Text');
		$modelClassName = Vtiger_Loader::getComponentClassName('Model', 'TransferOwnership', $moduleName);
		$transferModel = new $modelClassName();
		if ($request->isEmpty('record', true)) {
			$recordIds = Vtiger_Mass_Action::getRecordsListFromRequest($request);
		} else {
			$recordIds = [$request->getInteger('record')];
		}
		$configMaxTransferRecords = App\Config::performance('maxMassTransferOwnershipRecords');
		if (\count($recordIds) > $configMaxTransferRecords) {
			$response = new Vtiger_Response();
			$response->setResult(['notify' => ['text' => \App\Language::translateArgs('LBL_SELECT_UP_TO_RECORDS', '_Base', $configMaxTransferRecords), 'type' => 'error']]);
			$response->emit();
			return;
		}
		if (!empty($recordIds)) {
			$transferModel->transferRecordsOwnership($moduleName, $transferOwnerId, $recordIds);
		}
		if (!empty($relatedModules)) {
			foreach ($relatedModules as $relatedData) {
				$explodedData = explode('::', $relatedData);
				$relatedModule = current($explodedData);
				$relatedModuleRecordIds = $transferModel->getRelatedModuleRecordIds($request, $recordIds, $relatedData);
				if (!empty($relatedModuleRecordIds)) {
					$transferModel->transferRecordsOwnership($relatedModule, $transferOwnerId, $relatedModuleRecordIds);
				}
			}
		}
		$response = new Vtiger_Response();
		$response->setResult(['success' => true]);
		$response->emit();
	}
}
