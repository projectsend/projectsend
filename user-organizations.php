<?php
/**
 * Show the list of current groups.
 *
 * @package     ProjectSend
 * @subpackage  Groups
 *
 */
$load_scripts   = array(
                        'footable'
                    ); 

$allowed_levels = array(9);
require_once('sys.includes.php');

$active_nav = 'organizations';
$cc_active_page = 'Manage Organization';

$page_title = __('User Organizations Listing','cftp_admin');;

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
//  print_r($user_organizations);
//  print_r($client_organizations);


    
include('header.php');

?>
<div id="main">
<div id="content">
<div class="container-fluid">
<div class="row">
<h2><?php echo $page_title; ?></h2>

<section id="no-more-tables">
    <div class="col-md-12">
        <h3>Current Organizations</h3>
            <?php if(!empty($my_categories)){?>
            <table id="groups_tbl" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
            <thead>
            <tr>
            <th data-sort-initial="true"><?php _e('User Organization Name','cftp_admin'); ?></th>
            <th data-hide="phone"><?php _e('Description','cftp_admin'); ?></th>
            <th data-hide="phone"><?php _e('Status','cftp_admin'); ?></th>
            <th data-hide="action"><?php _e('Action','cftp_admin'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
/*          echo "<pre>";
            print_r($my_categories);
            echo "</pre>";
            exit;*/
            
            foreach($my_categories as $cat){
            ?>
            <tr>
            <td> <?php _e(html_output($cat["name"]),'cftp_admin'); ?></td>
            <td><?php echo html_output($cat["description"]); ?></td>
            <td><?php if($cat['m_org_status'] === 0) {echo "Pending";} else if($cat['m_org_status'] == 1) { echo "Active";} else {echo "Active";} ?></td>
            <td><span class="btn btn-danger btn-small remove_category"  id="<?php echo "del_".$cat["id"]."_".$cat["client_id"];?>">remove</span></td>
            </tr>
            <?php
            }
            }else{
                echo "No organization in the list!!";
            }
            ?>
            </tbody>
            </table>

    </div>
</section>
</div>
</div>
</div>
</div>
<?php include('footer.php'); ?>


<style type="text/css">
/*-------------------- Responsive table by B) -----------------------*/
@media only screen and (max-width: 1200px) {
    #content {
        padding-top:30px;
    }
    
    /* Force table to not be like tables anymore */
    #no-more-tables table, 
    #no-more-tables thead, 
    #no-more-tables tbody, 
    #no-more-tables th, 
    #no-more-tables td, 
    #no-more-tables tr { 
        display: block; 
    }
 
    /* Hide table headers (but not display: none;, for accessibility) */
    #no-more-tables thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
 
    #no-more-tables tr { border: 1px solid #ccc; }
 
    #no-more-tables td { 
        /* Behave  like a "row" */
        border: none;
        border-bottom: 1px solid #eee; 
        position: relative;
        padding-left: 50%; 
        white-space: normal;
        text-align:left;
    }
 
    #no-more-tables td:before { 
        /* Now like a table header */
        position: absolute;
        /* Top/left values mimic padding */
        top: 6px;
        left: 6px;
        width: 45%; 
        padding-right: 10px; 
        white-space: nowrap;
        text-align:left;
        font-weight: bold;
    }
 
    /*
    Label the data
    */

    
    td:nth-of-type(1):before { content: "User Organization Name"; }
    td:nth-of-type(2):before { content: "Description"; }
    td:nth-of-type(3):before { content: "Status"; }
    td:nth-of-type(4):before { content: "Action"; }
}
/*-------------------- Responsive table End--------------------------*/
</style>


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
                if(data == "done"){
                    alert('deleted successfully!!');    
                    location.reload();
                }else{
                    alert("Not deleted successfully!!");
                }
            
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
                if(data == "done"){
                    alert('added successfully!!');  
                    location.reload();
                }else{
                    alert("Not added successfully!!");
                }
            
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
