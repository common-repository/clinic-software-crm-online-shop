<?php

namespace ClinicSoftware\Lib\Entities\Contracts;

interface ImportableContract {

	public function import( $limit, $offset, $lastDate, $duplicate );
}
