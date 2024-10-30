<?php

namespace ClinicSoftware\Lib\Entities\Contracts;

interface ImportableInBatchesContract {

	public function importInBatches();
	public static function initImports();
}
