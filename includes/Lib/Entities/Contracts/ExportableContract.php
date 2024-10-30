<?php

namespace ClinicSoftware\Lib\Entities\Contracts;

interface ExportableContract {

	public function export( $limit, $offset, $lastDate );
}
