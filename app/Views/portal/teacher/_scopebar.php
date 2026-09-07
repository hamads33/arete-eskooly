<?php /** Shared class picker for teacher workspace pages. $self = current path. */ ?>
<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">-- Select a class --</option><?php foreach($scope as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?><?=$c['whole_class']?' (whole class)':''?></option><?php endforeach?></select></div>
<?php if(!empty($showDate)): ?><div class="module-field"><label for="date">Date</label><input class="module-input" id="date" name="date" type="date" value="<?=e($date)?>"></div><?php endif; ?>
<div class="filter-actions"><button class="btn btn--primary">Continue</button></div>
</form>
