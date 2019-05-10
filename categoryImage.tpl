<div class="row categoryImg">
  {foreach name=outer item=contact from=$datos}
    <div class="col-md-6 col-lg-4">
      <a href="{$link->getCategoryLink($contact.id_category)}">
        <img src="{$smarty.const._THEME_CAT_DIR_|escape:'quotes':'UTF-8'}{$contact.id_category}.jpg" class="imgCat">
        <h1>{$contact.title}</h1>
      </a>
    </div>
  {/foreach}
</div>