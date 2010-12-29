<tr id="rw_star_color" class="rw-odd"<?php if ($rw_options->type == "nero") echo ' style="display: none;"';?>>
    <td><span class="rw-ui-def">Color:</span></td>
    <td>
        <?php
            $colors = array("yellow", "red", "green", "blue", "gray", "custom");
            
            foreach ($colors as $i => $color)
            {
        ?>
        <div class="rw-ui-img-radio<?php if ($rw_options->color == $color) echo " rw-selected";?>" onclick="<?php if ($color == "custom") echo "RWM.showCustomWindow();";?>rwStar.setColor('<?php echo $color . "'"; if ($color != "custom") echo ");"; else echo ", jQuery('#rw_custom_url').val());";?>">
            <i class="rw-ui-holder"><i class="rw-ui-sprite rw-ui-star rw-ui-large rw-ui-<?php echo $color; if ($color == "yellow") echo " rw-ui-default";?>"></i></i>
            <span style="margin-right: 5px;"><?php echo ucwords(($color != "yellow") ? $color : "default");?></span>
            <input type="radio" name="rw-color" value="<?php echo $i;?>"<?php if ($rw_options->color == $color) echo ' checked="checked"';?> />
        </div>
        <?php
            }
        ?>
    </td>
</tr>