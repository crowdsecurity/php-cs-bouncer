<?php
require_once(__DIR__ . '/_base.php');
function displayAccessForbiddenTemplate()
{
    crowdSecBaseTemplatePart1();
    crowdSecBaseTemplatePart2();
?>
    <h1>🤭 Oh!</h1>
    <p class="desc">This page is protected against cyber attacks and your IP has been banned by our system.</p>
<?php crowdSecBaseTemplatePart3();
} ?>