<?php
  //$crontime = '1,3,4,5 6-12/3 * 12 1';
    $crontime = '* 6-19 * 10 *';
	$cron = new CronDue();
	$now = time();
	$is_due = $cron->isDue($now,$crontime);
	var_dump($is_due);
?>
