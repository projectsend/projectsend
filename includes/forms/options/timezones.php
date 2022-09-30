<?php
/**
 * Generates the select html field used on the options page.
 * Based off code found on a comment on the official PHP page.
 *
 * @author		vats_tco at comcast dot net
 * @copyright	19-Nov-2007
 * @link		http://php.net/manual/es/function.timezone-identifiers-list.php
 * @package		ProjectSend
 * 
 */
?>
<select class="form-select" id="timezone" name="timezone" required>
	<?php
		function timezonechoice($selectedzone) {

			$all = timezone_identifiers_list();
			$i = 0;

			foreach($all as $zone) {
				$zone = explode('/',$zone);
				$zonen[$i]['continent'] = isset($zone[0]) ? $zone[0] : '';
				$zonen[$i]['city'] = isset($zone[1]) ? $zone[1] : '';
				$zonen[$i]['subcity'] = isset($zone[2]) ? $zone[2] : '';
				$i++;
			}
		
			asort($zonen);
			$structure = '';

			foreach($zonen AS $zone) {
				extract($zone);

				if ($continent == 'Africa'
					|| $continent == 'America'
					|| $continent == 'Antarctica'
					|| $continent == 'Arctic'
					|| $continent == 'Asia'
					|| $continent == 'Atlantic'
					|| $continent == 'Australia'
					|| $continent == 'Europe'
					|| $continent == 'Indian'
					|| $continent == 'Pacific'
				) {
					if(!isset($selectcontinent)) {
						$structure .= '<optgroup id="opt_'.$continent.'" label="'.$continent.'">'."\n"; /** Continent */
					} elseif($selectcontinent != $continent) {
						$structure .= '</optgroup>'."\n".'<optgroup label="'.$continent.'">'."\n"; /** Continent */
					}
			
					if(isset($city) != ''){
						if (!empty($subcity) != ''){
							$city = $city . '/'. $subcity;
						}
						$structure .= "\t<option ".((($continent.'/'.$city)==$selectedzone)?'selected="selected" ':'')."value=\"".($continent.'/'.$city)."\">".str_replace('_',' ',$city)."</option>\n"; /** Timezone */
					} else {
						if (!empty($subcity) != ''){
							$city = $city . '/'. $subcity;
						}
						$structure .= "\t<option ".(($continent==$selectedzone)?'selected="selected" ':'')."value=\"".$continent."\">".$continent."</option>\n"; /** Timezone */
					}
					$selectcontinent = $continent;
				}
			}

			$structure .= '</optgroup>';

			return $structure;
		}

		echo timezonechoice(get_option('timezone'));
	?>
</select>