<?php
/**
 * Show the form to add a new group.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$load_scripts	= array(
	'chosen',
); 

$allowed_levels = array(9);
require_once('sys.includes.php');

$active_nav = 'category';

$page_title = __('Organization Request','cftp_admin');
$cc_active_page = 'Organization Request';

include('header.php');?>

<?php

?>

<div id="main">
	
	<div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
				<div class="white-box-interior">
				<h2 class="page-title txt-color-blueDark"><?php echo $page_title; ?></h2>
                <?php
	//SELECT TB.id as memberid,TU.*,TG.* FROM `tbl_members` AS TB INNER JOIN tbl_users AS TU ON TU.id=TB.client_id INNER JOIN tbl_groups AS TG ON TG.id=TB.group_id WHERE TB.`m_org_status`= 0
	$orgstatus = $dbh->query("SELECT TB.id as memberid,TU.*,TG.name as groupname FROM `tbl_members` AS TB INNER JOIN tbl_users AS TU ON TU.id=TB.client_id INNER JOIN tbl_groups AS TG ON TG.id=TB.group_id WHERE TB.`m_org_status`= 0" )->fetchAll(PDO::FETCH_ASSOC);

?>
<?php if($orgstatus) { ?>
<table id="groups_tbl" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			<thead>
			<tr>
			<th data-sort-initial="true"><?php _e('Organization name','cftp_admin'); ?></th>
			<th data-hide="user"><?php _e('Requested By','cftp_admin'); ?></th>
            <th data-hide="email"><?php _e('Email','cftp_admin'); ?></th>
            <th data-hide="phone"><?php _e('Phone','cftp_admin'); ?></th>
            <th><?php _e('Action','cftp_admin'); ?></th>
			</tr>
			</thead>
			<tbody>
<?php				
                foreach($orgstatus as $org) { ?>
                <tr>
                <td><?php echo $org['groupname'];?></td>
                <td><?php echo $org['user'];?></td>
                <td><?php echo $org['email'];?></td>
                 <td><?php echo $org['phone'];?></td>
                  <td>
                  <button data-memberid="<?php echo $org['memberid'];?>" class="btn btn-info btn-sm admin-org-activate">Activate</button>
                  <button data-memberid="<?php echo $org['memberid'];?>" class="btn btn-danger btn-sm admin-org-remove">Remove</button>
                  </td>
                </tr>
					
				<?php }
				?>
                </tbody>
                </table>
<?php } 
else {
	echo "No Request Found";
}?>
				</div>
			</div>
		</div>
	</div>
</div>
</div>
<?php
	include('footer.php');
?>
<script type="text/javascript">
	$(document).ready(function(){
		$(".admin-org-remove").click(function(event){
			var m_id = $(this).data('memberid');
		    if (confirm(" Are you sure you want to delete this item ?") == true) {
			$.ajax({
				type:'POST',
				url:'ajax.php',
				dataType:'html',
				data:{m_id:m_id,action:"admin_remove_organization"}
			}).done(function(data){
				if(data == "done"){
					alert('deleted successfully!!');	
					location.reload();
				}else{
					alert("Not deleted successfully!!");
				}
			
			});
		    } 
		});

		$(".admin-org-activate").click(function(event){
			var m_id = $(this).data('memberid');
			//alert(m_id);
			$.ajax({
				type:'POST',
				url:'ajax.php',
				dataType:'html',
				data:{m_id:m_id,action:"admin_activate_user_organization"}
			}).done(function(data){
				if(data == "done"){
					alert('added successfully!!');	
					location.reload();
				}else{
					alert("Not added successfully!!");
				}
			
			});
		    
		});
	});
</script>
