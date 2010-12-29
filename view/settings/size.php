<tr id="rw_star_size" class="rw-even"<?php if ($rw_options->type == "nero") echo ' style="display: none;"';?>>
    <td class="rw-ui-def-width">
        <span class="rw-ui-def">Size:</span>
    </td>
    <td>
        <?php
            $sizes = array("small", "medium", "large");
            foreach ($sizes as $size)
            {
        ?>
        <div class="rw-ui-img-radio<?php if ($rw_options->size == $size) echo " rw-selected";?>" onclick="RWM.Set.size(RW.SIZE.<?php echo strtoupper($size);?>);">
            <i class="rw-ui-holder"><i class="rw-ui-sprite rw-ui-star rw-ui-<?php echo strtolower($size);?> rw-ui-yellow"></i></i>
            <span><?php echo ucwords($size);?></span>
            <input type="radio" name="rw-size" value="0"<?php if ($rw_options->size == $size) echo ' checked="checked"';?> />
        </div>
        <?php
            }
        ?>
    </td>
</tr>
