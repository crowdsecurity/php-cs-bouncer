<?php
require_once(__DIR__ . '/_base.php');
crowdSecBaseTemplatePart1() ?><style>
    input {
        margin-top: 10px;
        padding: 10px;
        font-size: 1.1em;
        width: 150px;
    }

    button {
        margin: 30px 0 30px;
        padding: 10px 0;
        font-size: 1.1em;
        background-color: #626365;
        color: white;
        border: none;
        width: 150px;
        border-radius: 5px;
    }

    button:hover {
        background-color: #333;
        cursor: pointer;
    }

    img {
        padding: 10px;
    }

    .error {
        color: #b90000;
        padding: 5px;
    }
</style>
<?php crowdSecBaseTemplatePart2() ?>
<h1>
    <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="exclamation-triangle" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" class="warning">
        <path fill="#f39b2f" d="M569.517 440.013C587.975 472.007 564.806 512 527.94 512H48.054c-36.937 0-59.999-40.055-41.577-71.987L246.423 23.985c18.467-32.009 64.72-31.951 83.154 0l239.94 416.028zM288 354c-25.405 0-46 20.595-46 46s20.595 46 46 46 46-20.595 46-46-20.595-46-46-46zm-43.673-165.346l7.418 136c.347 6.364 5.609 11.346 11.982 11.346h48.546c6.373 0 11.635-4.982 11.982-11.346l7.418-136c.375-6.874-5.098-12.654-11.982-12.654h-63.383c-6.884 0-12.356 5.78-11.981 12.654z"></path>
    </svg>
    Hmm, sorry but...
</h1>
<p class="desc">Please complete the security check.</p>

<img src="<?php echo $captchaImageSrc ?>" alt="captcha to fill" />
<p><small><a href="#" onclick="newImage()">refresh image</a></small></p>

<form method="post" id="captcha" action="<?php echo $captchaResolutionFormUrl ?>">
    <input type="text" name="phrase" placeholder="Type here..." autofocus autocomplete="off" />
    <input type="hidden" name="crowdsec_captcha" value="1">
    <input type="hidden" name="refresh" value="0" id="refresh">
    <?php if ($error) : ?><p class="error">Please try again.</p><?php endif; ?>

    <button type="submit" />CONTINUE</button>
</form>
<?php crowdSecBaseTemplatePart3() ?>