<?php
    function rw_enrich_options(&$settings, $dictionary = array(), $dir = "ltr", $hor = "right", $type = "star")
    {
        $settings = @rw_get_default_value($settings, new stdClass());
        $settings->boost = @rw_get_default_value($settings->boost, new stdClass());
        $settings->advanced = @rw_get_default_value($settings->advanced, new stdClass());
        $settings->advanced->font = @rw_get_default_value($settings->advanced->font, new stdClass());
        $settings->advanced->layout = @rw_get_default_value($settings->advanced->layout, new stdClass());
        $settings->advanced->text = @rw_get_default_value($settings->advanced->text, new stdClass());
        $settings->advanced->layout->align = @rw_get_default_value($settings->advanced->layout->align, new stdClass());
        
        $settings->type = @rw_get_default_value($settings->type, $type);
        $settings->size = @rw_get_default_value($settings->size, "small");
        $settings->color = @rw_get_default_value($settings->color, "yellow");
        $settings->url = @rw_get_default_value($settings->url, "");
        $settings->readOnly = @rw_get_default_value($settings->readOnly, false);
        $settings->beforeRate = @rw_get_default_value($settings->beforeRate, null);
        $settings->afterRate = @rw_get_default_value($settings->beforeRate, null);
        
        $settings->boost->votes = @rw_get_default_value($settings->boost->votes, 0);
        $settings->boost->rate = @rw_get_default_value($settings->boost->rate, 5);

        $settings->advanced->font->bold = @rw_get_default_value($settings->advanced->font->bold, false);
        $settings->advanced->font->italic = @rw_get_default_value($settings->advanced->font->italic, false);
        $settings->advanced->font->color = @rw_get_default_value($settings->advanced->font->color, "#000000");
        $settings->advanced->font->size = @rw_get_default_value($settings->advanced->font->size, "12px");
        $settings->advanced->font->type = @rw_get_default_value($settings->advanced->font->type, "arial");

        $settings->advanced->layout->dir = @rw_get_default_value($settings->advanced->layout->dir, $dir);
        $settings->advanced->layout->lineHeight = @rw_get_default_value($settings->advanced->layout->lineHeight, "16px");
        $settings->advanced->layout->align->hor = @rw_get_default_value($settings->advanced->layout->align->hor, $hor);
        $settings->advanced->layout->align->ver = @rw_get_default_value($settings->advanced->layout->align->ver, "middle");

        $settings->advanced->text->rateAwful = @rw_get_default_value($settings->advanced->text->rateAwful, $dictionary["rateAwful"]);
        $settings->advanced->text->ratePoor = @rw_get_default_value($settings->advanced->text->ratePoor, $dictionary["ratePoor"]);
        $settings->advanced->text->rateAverage = @rw_get_default_value($settings->advanced->text->rateAverage, $dictionary["rateAverage"]);
        $settings->advanced->text->rateGood = @rw_get_default_value($settings->advanced->text->rateGood, $dictionary["rateGood"]);
        $settings->advanced->text->rateExcellent = @rw_get_default_value($settings->advanced->text->rateExcellent, $dictionary["rateExcellent"]);
        $settings->advanced->text->rateThis = @rw_get_default_value($settings->advanced->text->rateThis, $dictionary["rateThis"]);
        $settings->advanced->text->like = @rw_get_default_value($settings->advanced->text->like, $dictionary["like"]);
        $settings->advanced->text->dislike = @rw_get_default_value($settings->advanced->text->dislike, $dictionary["dislike"]);
        $settings->advanced->text->vote = @rw_get_default_value($settings->advanced->text->vote, $dictionary["vote"]);
        $settings->advanced->text->votes = @rw_get_default_value($settings->advanced->text->votes, $dictionary["votes"]);
        $settings->advanced->text->thanks = @rw_get_default_value($settings->advanced->text->thanks, $dictionary["thanks"]);
    }
        
    function rw_get_default_value($val, $def)
    {
        return (isset($val) ? $val : $def);
    }
?>
