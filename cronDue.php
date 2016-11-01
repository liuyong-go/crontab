<?php

/**
 * 定时任务判断执行
 * User: liuyong
 * Date: 2016/10/28
 * Time: 13:54
 */
class CronDue
{
    /**
     * @var array
     */
    private $_fieldsFunc = [
        '0'=> 'minutes',
        '1'=> 'hour',
        '2'=> 'dayOfMonth',
        '3'=> 'month',
        '4'=> 'dayOfWeek'
    ];
    /**
     * 判断的时间
     */
    private  $_valiTime='now';
    public function __construct()
    {
    }

    /**
     * 校验当前cron是否满足执行时间
     * @param int $now  当前时间,时间戳
     * @param string $crontime  定时任务时间
     * @return bool|mixed
     */
    public function isDue($now,$crontime){
        $this->_valiTime = date('Y-m-d H:i:s',$now);
        $fields = array_reverse(explode(' ',$crontime),true); //反转，遍历判断
        $satisfied = true;
        foreach($fields as $key=>$part){
            $evalFunc = $this->_fieldsFunc[$key].'Satisfy';
            if (strpos($part, ',') === false) {
                eval("\$satisfied=\$this->".$evalFunc."('".$part."');");
            }else{
                $satisfied = false;
                foreach (array_map('trim', explode(',', $part)) as $listPart) {
                    eval("\$satisfied=\$this->".$evalFunc."(".$listPart.");");
                    if($satisfied == true){
                        break;
                    }
                }
            }
            if($satisfied == false){
                break;
            }
        }
        return $satisfied;
    }

    /**
     * 判断周
     * 判断字符 * , -  L # /
     * @param string $part
     * @return bool
     */
    public function dayOfWeekSatisfy($value){
        $date = new DateTime($this->_valiTime);
        $value = str_ireplace(['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'], range(0, 6), $value);
        $currentYear = $date->format('Y');
        $currentMonth = $date->format('m');
        $lastDayOfMonth = (int)$date->format('t');

        //判断是不是这个月的最后的一个周几
        if (strpos($value, 'L')) {
            $weekday = str_replace('7', '0', substr($value, 0, strpos($value, 'L')));
            $tdate = clone $date;
            $tdate->setDate($currentYear, $currentMonth, $lastDayOfMonth);
            while ($tdate->format('w') != $weekday) {
                $tdateClone = new \DateTime();
                $tdate = $tdateClone
                    ->setTimezone($tdate->getTimezone())
                    ->setDate($currentYear, $currentMonth, --$lastDayOfMonth);
            }

            return $date->format('j') == $lastDayOfMonth;
        }

        // Handle # hash tokens
        if (strpos($value, '#')) {
            list($weekday, $nth) = explode('#', $value);

            // 0和7 都是 周日
            if ($weekday === '0') {
                $weekday = 7;
            }

            if ($weekday < 0 || $weekday > 7) {
                return false;
            }
            if ($nth > 5) {
                return false;
            }
            if ($date->format('N') != $weekday) {
                return false;
            }

            $tdate = clone $date;
            $tdate->setDate($currentYear, $currentMonth, 1);
            $dayCount = 0;
            $currentDay = 1;
            $lastDayOfMonth = $lastDayOfMonth+1;
            while ($currentDay < $lastDayOfMonth) {
                if ($tdate->format('N') == $weekday) {
                    if (++$dayCount >= $nth) {
                        break;
                    }
                }
                $tdate->setDate($currentYear, $currentMonth, ++$currentDay);
            }

            return $date->format('j') == $currentDay;
        }

        // Handle day of the week values
        if (strpos($value, '-')) {
            $parts = explode('-', $value);
            if ($parts[0] == '7') {
                $parts[0] = '0';
            } elseif ($parts[1] == '0') {
                $parts[1] = '7';
            }
            $value = implode('-', $parts);
        }

        // Test to see which Sunday to use -- 0 == 7 == Sunday
        $format = in_array(7, str_split($value)) ? 'N' : 'w';
        $fieldValue = $date->format($format);

        return $this->isSatisfied($fieldValue, $value);
    }
    /**
     * 判断月
     * 判断字符 * , - /
     * @param string $part
     * @return bool
     */
    public  function monthSatisfy($value){
        $value = str_ireplace([ 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'], range(1, 12),$value);
        $dateTime = date('m',strtotime($this->_valiTime));
        return $this->isSatisfied($dateTime, $value);
    }
    /**
     * 判断天
     * 判断字符 * , -  L W /
     * @param string $part
     * @return bool
     */
    public  function dayOfMonthSatisfy($value){
        if ($value == '?') {
            return true;
        }
        $date = new DateTime($this->_valiTime);
        $fieldValue = $date->format('d');

        //检查是不是月份的最后一天
        if ($value == 'L') {
            return $fieldValue == $date->format('t');
        }

        // 某天最近的一个工作日
        if (strpos($value, 'W')) {
            // Parse the target day
            $targetDay = substr($value, 0, strpos($value, 'W'));
            // Find out if the current day is the nearest day of the week
            return $date->format('j') == $this->_getNearestWeekday($date->format('Y'), $date->format('m'), $targetDay)->format('j');
        }

        return $this->isSatisfied($date->format('d'), $value);
    }

    /**
     * 获取最近一个周几
     * @param $currentYear
     * @param $currentMonth
     * @param $targetDay
     * @return DateTime
     */
    private  function _getNearestWeekday($currentYear, $currentMonth, $targetDay)
    {
        $tday = str_pad($targetDay, 2, '0', STR_PAD_LEFT);
        $target = DateTime::createFromFormat('Y-m-d', "$currentYear-$currentMonth-$tday");
        $currentWeekday = (int) $target->format('N');

        if ($currentWeekday < 6) {
            return $target;
        }

        $lastDayOfMonth = $target->format('t');

        foreach (array(-1, 1, -2, 2) as $i) {
            $adjusted = $targetDay + $i;
            if ($adjusted > 0 && $adjusted <= $lastDayOfMonth) {
                $target->setDate($currentYear, $currentMonth, $adjusted);
                if ($target->format('N') < 6 && $target->format('m') == $currentMonth) {
                    return $target;
                }
            }
        }
    }
    /**
     * 判断时
     * 判断字符 * , - /
     * @param string $part
     * @return bool
     */
    public  function hourSatisfy($value){
        $dateTime = date('H',strtotime($this->_valiTime));
        return $this->isSatisfied($dateTime, $value);
    }
    /**
     * 判断分
     * 判断字符 * , - /
     * @param string $part
     * @return bool
     */
    public function minutesSatisfy($value){
        $dateTime = date('i',strtotime($this->_valiTime));
        return $this->isSatisfied($dateTime, $value);
    }
    /**
     * 公共校验部分
     * 校验基本相等和 范围 和 /
     */
    public function isSatisfied($dateValue,$value){
        if (strpos($value, '/') !== false) {
            return $this->isInIncrementsOfRanges($dateValue, $value);
        } elseif (strpos($value, '-') !== false) {
            return $this->isInRange($dateValue, $value);
        }
        return $value == '*' || $dateValue == $value;
    }

    /**
     * 判断  / step
     * @param $dateValue
     * @param $value
     * @return bool
     */
    public function isInIncrementsOfRanges($dateValue, $value)
    {
        $parts = array_map('trim', explode('/', $value, 2));
        $stepSize = isset($parts[1]) ? $parts[1] : 0;
        if (($parts[0] == '*' || $parts[0] === '0') && 0 !== $stepSize) {
            return (int) $dateValue % $stepSize == 0;
        }

        $range = explode('-', $parts[0], 2);
        $offset = $range[0];
        $to = isset($range[1]) ? $range[1] : $dateValue;
        // Ensure that the date value is within the range
        if ($dateValue < $offset || $dateValue > $to) {
            return false;
        }

        if ($dateValue > $offset && 0 === $stepSize) {
            return false;
        }

        for ($i = $offset; $i <= $to; $i+= $stepSize) {
            if ($i == $dateValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * 范围判断
     * @param $dateValue
     * @param $value
     * @return bool
     */
    public function isInRange($dateValue, $value)
    {
        $parts = array_map('trim', explode('-', $value, 2));

        return $dateValue >= $parts[0] && $dateValue <= $parts[1];
    }
}
