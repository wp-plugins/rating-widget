<table id="rw_text_settings" cellspacing="0" style="display: none;">
<?php
    $odd = true;
    $i = 0;
    require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/languages/en.php");
    foreach ($rw_options->advanced->text as $key => $phrase)
    {
?>
    <tr id="rw_text_<?php echo $key;?>" class="rw-<?php echo ($odd) ? "odd" : "even"; ?>">
        <td<?php if ($i == 0) echo ' class="rw-ui-def-width"';?>>
            <span class="rw-ui-def"><?php echo $dictionary[$key];?>:</span>
        </td>
        <td>
            <input onfocus="var e = this; setTimeout(function(){jQuery(e).select();}, 100);" onblur="RWM.Set.text('<?php echo $key;?>');" type="text" id="rw_text_input_<?php echo $key;?>" value="<?php echo $phrase;?>" />
        </td>
    </tr>
<?php
        $odd = !$odd;
        $i++;
    }
?>
</table>
