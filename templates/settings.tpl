<link rel="stylesheet" href="{$ApplicationUrl}includes/styles/stylesheet.css" type="text/css">
<div style="padding:0; margin:0;">
  <form name="frmAddonsPmtabounceSettings" method="post" action="{$SettingsUrl}&SubAction=SaveSettings" target="_parent" style="padding:0; margin:0;">
    <table width="100%" cellspacing="0" cellpadding="2" border="0">
      <tr>
        <td class="FieldLabel" style="width:2000px;">
          {$lang.Addon_pmtabounce_Directory}
        </td>
        <td>
          <input type="text" name="directory" value="{$directory}" size="40" maxlength="80" />
        </td>
      </tr>
      <tr>
        <td class="FieldLabel" style="width:200px;">
          {$lang.Addon_pmtabounce_After}
        </td>
        <td class="Field">
          <label for="delete_after"><input type="checkbox" id="delete_after" name="delete_after" value="1" {if $delete_after}checked="checked"{/if}/>{$lang.Addon_pmtabounce_Delete}</label>
        </td>
      </tr>
      <tr>
        <td class="FieldLabel" style="width:200px;">&nbsp;</td>
        <td>
          <input class="FormButton SubmitButton" type="submit" value="{$lang.Addon_pmtabounce_Save}" />
        </td>
      </tr>
    </table>
  </form>
</div>
