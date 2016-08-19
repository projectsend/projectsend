<?php
	require_once('sys.includes.php');

	$allowed_stats = array(9,8,7);
	if (in_array(CURRENT_USER_LEVEL,$allowed_stats)) {
		$max_stats_days = $_GET['days'];
	
		$month = date("m");
		$day = date("d");
		$year = date("Y");
		for($i=0; $i<=$max_stats_days-1; $i++){
			//$gen_30_days[] = date(TIMEFORMAT_USE,mktime(0,0,0,$month,($day-$i),$year));
			$gen_30_days[] = date('d/m/Y',mktime(0,0,0,$month,($day-$i),$year));
		}
		$last_30_days = array_reverse($gen_30_days);
	
		/**
		 * The graph will show only this actions
		 */
		$statement = $dbh->prepare("SELECT action, timestamp, COUNT(*) as total
										FROM " . TABLE_LOG . " 
										WHERE timestamp >= DATE_SUB( CURDATE(),INTERVAL :max_days DAY)
										AND action IN ('5', '6', '8', '9')
										GROUP BY DATE(timestamp), action
									");
		$params = array(
						':max_days'	=> $max_stats_days,
					);
		$statement->execute( $params );
		if ( $statement->rowCount() > 0 ) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $res = $statement->fetch() ) {
				$res['timestamp'] = strtotime($res['timestamp']);
				switch ($res['action']) {
					case 5:
						$actions_to_graph['d5'][$res['timestamp']] = $res['total'];
						break;
					case 6:
						$actions_to_graph['d6'][$res['timestamp']] = $res['total'];
						break;
					case 8:
						$actions_to_graph['d8'][$res['timestamp']] = $res['total'];
						break;
					case 9:
						$actions_to_graph['d9'][$res['timestamp']] = $res['total'];
						break;
				}
			}
			$continue = true;
		}
		else {
			$continue = false;
		}
	?>
	
	<script type="text/javascript">
	<?php	
			$data_logs = array('d5','d6','d8','d9');
			foreach ($data_logs as $gen_log) {
				echo 'var '.$gen_log.' = [';
				$i = 0;
				foreach ($last_30_days as $day) {
					$wrote = false;
					$day_timestamp = str_replace('/', '-',$day);
					$final_timestamp = strtotime($day_timestamp)*1000;
					echo "[".$final_timestamp.",";
					if (!empty($actions_to_graph)) {
						foreach ($actions_to_graph as $action_number => $when) {
							if ($action_number == $gen_log) {
								foreach ($when as $log_day => $total) {
									//if (date(TIMEFORMAT_USE,$log_day) == $day) {
									if (date('d/m/Y',$log_day) == $day) {
										echo $total;
										$wrote = true;
									}
									else {
									}
								}
							}
						}
					}
					if (!$wrote || $continue === false) {
						echo '0';
						/** These values are used for screenshots
						switch ($gen_log) {
							case 'd5': echo rand(0,10); break;
							case 'd6': echo rand(0,30); break;
							case 'd8': echo rand(0,180); break;
							case 'd9': echo rand(0,45); break;
						}
						*/
					}
					echo ']';
					$i++;
					if ($i < $max_stats_days) {
						echo ',';
					}
				}
				echo "];\n";
			}
		?>
		function showTooltip(x, y, contents) {
			$('<div id="stats_tooltip">' + contents + '</div>').css( {
				top: y + 5,
				left: x + 5,
			}).appendTo("body").fadeIn(200);
		}
		
		var previousPoint = null;
		$("#statistics").bind("plothover", function (event, pos, item) {
			$("#x").text(pos.x.toFixed(2));
			$("#y").text(pos.y.toFixed(2));
		
				if (item) {
					if (previousPoint != item.dataIndex) {
						previousPoint = item.dataIndex;
						
						$("#stats_tooltip").remove();
						var x = item.datapoint[0].toFixed(2),
							y = item.datapoint[1].toFixed(2);
	
						showTooltip(item.pageX, item.pageY,
									item.series.label + ": " + y);
					}
				}
				else {
					$("#stats_tooltip").remove();
					previousPoint = null;            
				}
		});	
	
		var options = {
			grid: {
				hoverable: true,
				borderWidth: 0,
				color: "#666",
				labelMargin: 10,
				axisMargin: 0,
				mouseActiveRadius: 10
			},
			series: {
				lines: {
					show: true,
					lineWidth: 2
				},
				points: {
					show: true,
					radius: 3,
					symbol: "circle",
					fill: true
				}
			},
			xaxis: {
				mode: "time",
				minTickSize: [1, "day"],
				timeformat: "%d/%m",
				labelWidth: "30"
			},
			yaxis: {
				min: 0,
				tickDecimals:0
			},
			legend: {
				margin: 10,
				sorted: true,
				show: false
			},
			colors: ["#0094bb","#86ae00","#C60F13","#f2b705"]
		};
	
		$.plot(
			$("#statistics"), [
				{
					data: d5,
					label: '<?php _e('Uploads by users','cftp_admin'); ?>'
				},
				{
					data: d6,
					label: '<?php _e('Uploads by clients','cftp_admin'); ?>'
				},
				{
					data: d8,
					label: '<?php _e('Downloads','cftp_admin'); ?>'
				},
				{
					data: d9,
					label: '<?php _e('Zip Downloads','cftp_admin'); ?>'
				}
			], options
		);
	</script>

<?php
	}
?>