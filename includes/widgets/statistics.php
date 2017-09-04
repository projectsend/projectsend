<?php
	$render_container = true;
	if ( isset( $_GET['ajax_call'] ) ) {
		require_once('../../sys.includes.php');
		$render_container = false;
	}

	$days_buttons = array(15, 30, 60);
	$default_days = $days_buttons[0];
	$max_stats_days = isset( $_GET['days'] ) ? $_GET['days'] : $default_days;
	$demo_data = false;

	$legends = array(
					1 => array(
								'title'		=> __('Uploads by users'),
								'color'		=> "#0094bb",
								'action'	=> 5,
							),
					2 => array(
								'title'		=> __('Uploads by clients'),
								'color'		=> "#86ae00",
								'action'	=> 6,
							),
					3 => array(
								'title'		=> __('Downloads'),
								'color'		=> "#f2b705",
								'action'	=> 8,
							),
					4 => array(
								'title'		=> __('Public Downloads'),
								'color'		=> "#1ec4a7",
								'action'	=> 37,
							),
				);
	foreach ( $legends as $index => $info ) {
		$colors[] = '"' . $info['color'] . '"';
		// Downloads are queried separately
		if ( $info['action'] != 8 ) {
			$actions[] = '"' . $info['action'] . '"';
		}

		$all_actions[] = 'd' . $info['action'];
	}
	
	$colors = implode(',', $colors);

	$actions = implode(',', $actions);


	if ( $render_container == true ) {
?>
		<div class="widget">
			<h4><?php _e('Statistics','cftp_admin'); ?></h4>
			<div class="widget_int widget_statistics">
				<div class="stats_change_days">
					<?php
						foreach ( $days_buttons as $days ) {
					?>
							<a href="#" class="stats_days btn btn-sm btn-default <?php if ( $days == $default_days ) { echo 'btn-inverse'; } ?>" data-days="<?php echo $days; ?>">
								<?php echo $days . ' '; _e('days','cftp_admin'); ?>
							</a>
					<?php
						}
					?>
				</div>
				<ul class="graph_legend">
					<?php foreach ( $legends as $index => $info ) { ?>
						<li class="legend_color legend_color<?php echo $index; ?>" style="border-top:5px solid <?php echo $info['color']; ?>;">
							<div class="ref_color"></div>
							<?php echo $info['title']; ?>
						</li>
					<?php } ?>
				</ul>
		
				<div class="statistics_graph"></div>
			</div>
		</div>
<?php
	}

	$allowed_stats = array(9,8,7);
	if (in_array(CURRENT_USER_LEVEL,$allowed_stats)) {
		$month = date("m");
		$day = date("d");
		$year = date("Y");
		for($i=0; $i<=$max_stats_days-1; $i++){
			//$gen_30_days[] = date(TIMEFORMAT_USE,mktime(0,0,0,$month,($day-$i),$year));
			$gen_30_days[] = date('d/m/Y',mktime(0,0,0,$month,($day-$i),$year));
		}
		$last_30_days = array_reverse($gen_30_days);
		
		$actions_to_graph = array();

		$params = array(
						':max_days'	=> $max_stats_days,
					);
		/**
		 * Get downloads from the specific downloads table
		 */
		$statement = $dbh->prepare("SELECT timestamp, COUNT(*) as total
										FROM " . TABLE_DOWNLOADS . " 
										WHERE timestamp >= DATE_SUB( CURDATE(),INTERVAL :max_days DAY)
										GROUP BY DATE(timestamp)
									");
		$statement->execute( $params );
		if ( $statement->rowCount() > 0 ) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $res = $statement->fetch() ) {
				$res['timestamp'] = strtotime($res['timestamp']);
				$actions_to_graph['d8'][$res['timestamp']] = $res['total'];
			}
		}

		/**
		 * Get other details from the actions log
		 */
		$statement = $dbh->prepare("SELECT action, timestamp, COUNT(*) as total
										FROM " . TABLE_LOG . " 
										WHERE timestamp >= DATE_SUB( CURDATE(),INTERVAL :max_days DAY)
										AND action IN (".$actions.")
										GROUP BY DATE(timestamp), action
									");
		$statement->execute( $params );
		if ( $statement->rowCount() > 0 ) {
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			while ( $res = $statement->fetch() ) {
				$dkey = 'd'.$res['action'];
				$res['timestamp'] = strtotime($res['timestamp']);
				$actions_to_graph[$dkey][$res['timestamp']] = $res['total'];
			}
			$continue = true;
		}
		else {
			$continue = false;
		}
	?>
	<script type="text/javascript">
		$(document).ready(function(e) {
	<?php	
			foreach ($all_actions as $gen_log) {
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
						
						/** These values are used for screenshots */
						if ( $demo_data == true ) {
							switch ($gen_log) {
								case 'd5': echo rand(0,10); break;
								case 'd6': echo rand(0,30); break;
								case 'd8': echo rand(0,180); break;
								case 'd37': echo rand(0,45); break;
							}
						}
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
			$(".statistics_graph").bind("plothover", function (event, pos, item) {
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
				colors: [<?php echo $colors; ?>]
			};
		
			$.plot(
				$(".statistics_graph"), [
					<?php
						$l = 0;
						$count = count($legends);
						foreach ( $legends as $index => $legend ) {
					?>
							{
								data: d<?php echo $legend['action']; ?>,
								label: '<?php echo $legend['title']; ?>'
							}
					<?php
							$l++;
							if ( $l != $count ) { echo ','; }
						}
					?>
				], options
			);
		});
	</script>

<?php
	}
