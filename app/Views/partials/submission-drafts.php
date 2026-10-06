<?php /* Unsent answer pages saved on this device (submission-pages.js fills this in; hidden when there are none). */ ?>
<div data-submission-drafts data-owner="s<?= (int) ($student['id'] ?? 0) ?>" data-submitted="<?= e(implode(',', array_map('intval', $submittedTestIds ?? []))) ?>" hidden></div>
