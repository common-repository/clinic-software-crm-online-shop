<?php

namespace ClinicSoftware\Lib\Entities\Contracts;

interface ScheduleableContract {

	public function handleSchedule();
	public function getScheduleId(): string;
	public function getExportScheduleId(): string;
	public function getBatchSize(): int;
}
