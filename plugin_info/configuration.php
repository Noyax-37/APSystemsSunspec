<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}


  sendVarToJS('version', config::byKey('version', 'APSystemsSunspec', 'unknown', true));
  include_file('desktop', 'APSystemsSunspec.config', 'js', 'APSystemsSunspec');
  
  $core_version = 'x.y';
  
  if (!file_exists(dirname(__FILE__) . '/info.json')) {
    log::add('APSystemsSunspec','warning',__('Pas de fichier info.json', __FILE__));
  }
  $data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
  if (!is_array($data)) {
    log::add('APSystemsSunspec','warning',__('Impossible de décoder le fichier info.json', __FILE__));
  }
  try {
    $core_version = $data['pluginVersion'];
  } catch (\Exception $e) {
    log::add('APSystemsSunspec','warning',__('Impossible de récupérer la version.', __FILE__));
  }

?>
<form class="form-horizontal">
  <fieldset>
    <legend><i class="icon loisir-pacman1"></i> {{Version}}</legend>
    <div class="form-group">
        <label class="col-lg-4 control-label">Core APSystemsSunspec : <sup><i class="fas fa-question-circle tooltips" title="{{Version du plugin}}" style="font-size : 1em;color:grey;"></i></sup></label>
        <span style="top:6px;" class="col-lg-4"><?php echo $core_version; ?></span>
    </div>
  </fieldset>

</form>


