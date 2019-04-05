<?php
/**
 * Show the list of current groups.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$load_scripts	= array(
						'footable'
					); 

$allowed_levels = array(9,8,0);
require_once('sys.includes.php');

$active_nav = 'organizations';
$cc_active_page = 'Manage Organization';

$page_title = __('Client Organizations Listing','cftp_admin');;

/**
 * Used when viewing groups a certain client belongs to.
 */
if(!empty($_GET['id'])) {
	$id = intval($_GET['id']);
	/** Add the name of the client to the page's title. */
	$sql_name = $dbh->prepare("SELECT tm.client_id as client_id,tm.m_org_status,tg.name,tg.description,tg.id FROM ". TABLE_MEMBERS ." AS tm INNER JOIN ". TABLE_GROUPS ." AS tg ON tm.group_id=tg.id  WHERE tm.client_id=:id");
	$sql_name->bindParam(':id', $id, PDO::PARAM_INT);
	$sql_name->execute();
	
$categories= array();
	if ( $sql_name->rowCount() > 0) {
			$sql_name->setFetchMode(PDO::FETCH_ASSOC);
		while ( $row_member = $sql_name->fetch() ) {
		$my_categories[] = $row_member;
		}
	}
}	
	$user_organizations= array();
	//$sql = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE organization_type = '1' ");
	$sql = $dbh->prepare("SELECT * FROM ".TABLE_GROUPS." WHERE organization_type = 1 and id NOT IN (SELECT group_id FROM ".TABLE_MEMBERS." WHERE client_id =".$id.")");
	//var_dump($sql);
	//echo 'test';exit;
	$sql->execute();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	$user_organizations=$sql->fetchAll();
	$client_organizations= array();
	$sql = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE organization_type = '0' ");
	//var_dump($sql);
	//echo 'test';exit;
	$sql->execute();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	$client_organizations=$sql->fetchAll();
//	print_r($user_organizations);
//	print_r($client_organizations);


	
include('header.php');

?>
<div id="main">
<div id="content">
<div class="container-fluid">
<div class="row">
<h2><?php echo $page_title; ?></h2>

<section id="no-more-tables">
	<div class="col-md-12">
		<div class="col-md-6">
		<h3>Current Organizations</h3>
			<table id="groups_tbl" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			<thead>
			<tr>
			<th data-sort-initial="true"><?php _e('User Organization name','cftp_admin'); ?></th>
			<th data-hide="phone"><?php _e('Description','cftp_admin'); ?></th>
			<th data-hide="action"><?php _e('Action','cftp_admin'); ?></th>
            <td><?php echo "Status"; ?></td>
			</tr>
			</thead>
			<tbody>
			<?php
			if(!empty($my_categories)){
			foreach($my_categories as $cat){
				//print_r($cat);
			?>
			<tr>
			<td> <?php _e(html_output($cat["name"]),'cftp_admin'); ?></td>
			<td><?php echo html_output($cat["description"]); ?></td>
            <td><?php if($cat['m_org_status'] === '0')  {echo "Pending" ;} else { echo "Active";} ?></td>
			<td><span class="btn btn-danger btn-small remove_category"  id="<?php echo "del_".$cat["id"]."_".$cat["client_id"];?>">remove</span></td>
			</tr>
			<?php
			}
			}
			?>
			</tbody>
			</table>
		</div>
		<div class="col-md-6">
		<div class="col-md-12">
			<h3>Other Organizations</h3>
            <!------------------------------------------------------------------->
            <?php
if(!empty($_GET['id'])) {
	$id = intval($_GET['id']);
	/** Add the name of the client to the page's title. */
	$sql_other = $dbh->prepare("SELECT * FROM ".TABLE_GROUPS." WHERE organization_type = 0 and id NOT IN (SELECT group_id FROM ".TABLE_MEMBERS." WHERE client_id =".$id.")");
	$sql_other->execute();
	
$other_categories= array();
	if ( $sql_other->rowCount() > 0) {
			$sql_other->setFetchMode(PDO::FETCH_ASSOC);
		while ( $row_member = $sql_other->fetch() ) {
		$other_categories[] = $row_member;
		}
	}
}
/*echo "<pre>";
print_r($other_categories);
echo "</pre>";
exit;*/
			?>
            <!------------------------------------------------------------------->
			<table id="groups_tbl" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			<thead>
			<tr>
			<th data-sort-initial="true"><?php _e('Client Organization name','cftp_admin'); ?></th>
			<th data-hide="phone"><?php _e('Description','cftp_admin'); ?></th>
            <th><?php _e('Action','cftp_admin'); ?></th>
			</tr>
			</thead>
			<tbody>

			<?php
			if(!empty($other_categories)){
			foreach($other_categories as $cat){
			?>
			<tr>
			<td> <?php _e(html_output($cat["name"]),'cftp_admin'); ?></td>
			<td><?php echo html_output($cat["description"]); ?></td>
            <td><a href="#" data-orgid="<?php echo $cat["id"]; ?>" data-clientid="<?php echo $id; ?>" class="btn btn-success btn-sm add-client-cat">Add</a></td>
			</tr>

			<?php
			}
			}
			?>

			</tbody>
			</table>
		</div>


		</div>
		</div>
	</div>
</section>
</div>
</div>
</div>
</div>
<?php include('footer.php'); ?>
<script type="text/javascript">
	$(document).ready(function(){
		$(".remove_category").click(function(event){
			var event_id = event.target.id;
			//alert(event_id);
			var group_id = event_id.split('_')[1];
			//alert(group_id);
			var client_id = event_id.split('_')[2];
			//alert(client_id);
			//alert(group_id+" "+client_id);

		    if (confirm("Are you sure you want to delete this item ?") == true) {
			$.ajax({
				type:'POST',
				url:'ajax.php',
				dataType:'html',
				data:{group_id:group_id,client_id:client_id,action:"remove_organization"}
			}).done(function(data){
						alert('Deleted successfully!!');
						location.reload();

			});
		    } 
		});
		$(".add-client-cat").click(function(event){
				var cat_id = $(this).data('orgid');
				var client_id = $(this).data('clientid');
			$.ajax({
				type:'POST',
				url:'ajax.php',
				dataType:'html',
				data:{cat_id:cat_id,client_id:client_id,action:"add_new_client_organization"}
			}).done(function(data){

				alert('Added successfully!!');
				location.reload();

			});
		    
		});
		$(".add-user-cat").click(function(event){
				var cat_id = $(this).data('orgid');
				var client_id = $(this).data('clientid');
			$.ajax({
				type:'POST',
				url:'ajax.php',
				dataType:'html',
				data:{cat_id:cat_id,client_id:client_id,action:"add_new_user_organization"}
			}).done(function(data){
				alert('Added successfully!!');
				location.reload();
			});
		    
		});
	});
</script>
