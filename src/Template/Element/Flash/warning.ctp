<?php
$escape = isset($params['escape']) ? $params['escape'] : true;

if ($escape):
    $message = h($message);
endif;
?>
<div class="alert alert-dismissable alert-warning" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <?php echo $message; ?>
</div>
