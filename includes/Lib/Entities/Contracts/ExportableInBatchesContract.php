<?php

namespace ClinicSoftware\Lib\Entities\Contracts;

interface ExportableInBatchesContract {

	public function exportInBatches();
	public static function initExports();
}
