<?

date_default_timezone_set("Africa/Lagos");

function dateDifference($date1, $date2)
	{		
		$diff = abs($date1 - $date2);
		
		$day = $diff/(60*60*24); // in day
		$dayFix = floor($day);
		$dayPen = $day - $dayFix;
		if($dayPen > 0)
		{
			$hour = $dayPen*(24); // in hour (1 day = 24 hour)
			$hourFix = floor($hour);
			$hourPen = $hour - $hourFix;
			if($hourPen > 0)
			{
				$min = $hourPen*(60); // in hour (1 hour = 60 min)
				$minFix = floor($min);
				$minPen = $min - $minFix;
				if($minPen > 0)
				{
					$sec = $minPen*(60); // in sec (1 min = 60 sec)
					$secFix = floor($sec);
				}
			}
		}
		$str = "";
		if($dayFix > 0)
			$str.= $dayFix." day ";
		if($hourFix > 0)
			$str.= $hourFix." hour ";
		if($minFix > 0)
			$str.= $minFix." min ";
		if($secFix > 0)
			$str.= $secFix." sec ";
		return $str;
	}

