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

var liste_donnees = [{etiquette:"",name:""},
  {etiquette:"",name:"-------- Configuration PV ---------"},
  {etiquette:"pv_power",name:""},
  {etiquette:"pv1_power",name:""},
  {etiquette:"pv1_energy",name:""},
  {etiquette:"pv1_voltage",name:""},
  {etiquette:"pv1_current",name:""},
  {etiquette:"pv2_power",name:""},
  {etiquette:"pv2_energy",name:""},
  {etiquette:"pv2_voltage",name:""},
  {etiquette:"pv2_current",name:""},
  {etiquette:"pv3_power",name:""},
  {etiquette:"pv3_energy",name:""},
  {etiquette:"pv3_voltage",name:""},
  {etiquette:"pv3_current",name:""},
  {etiquette:"pv4_power",name:""},
  {etiquette:"pv4_energy",name:""},
  {etiquette:"pv4_voltage",name:""},
  {etiquette:"pv4_current",name:""},
  {etiquette:"pv5_power",name:""},
  {etiquette:"pv5_energy",name:""},
  {etiquette:"pv5_voltage",name:""},
  {etiquette:"pv5_current",name:""},
  {etiquette:"daily_solar",name:""},
  {etiquette:"",name:"-------- Configuration batterie ---------"},
  {etiquette:"battery_state",name:""},
  {etiquette:"battery_temp",name:""},
  {etiquette:"battery_voltage",name:""},
  {etiquette:"battery_current",name:""},
  {etiquette:"battery_power",name:""},
  {etiquette:"daily_battery_charge",name:""},
  {etiquette:"daily_battery_discharge",name:""},
  {etiquette:"battery_mppt_power",name:""},
  {etiquette:"battery_mppt_energy",name:""},
  {etiquette:"",name:"-------- Configuration Auxiliaire ---------"},
  {etiquette:"aux_power",name:""},
  {etiquette:"daily_aux",name:""},
  {etiquette:"",name:"-------- Configuration réseau ---------"},
  {etiquette:"daily_grid_buy",name:""},
  {etiquette:"daily_grid_sell",name:""},
  {etiquette:"grid_power",name:""},
  {etiquette:"grid_status",name:""},
  {etiquette:"",name:"-------- Configuration charge ---------"},
  {etiquette:"daily_load",name:""},
  {etiquette:"load_state",name:""},
  {etiquette:"load1_state",name:""},
  {etiquette:"load1_energy",name:""},
  {etiquette:"load2_state",name:""},
  {etiquette:"load2_energy",name:""},
  {etiquette:"load3_state",name:""},
  {etiquette:"load3_energy",name:""},
  {etiquette:"load4_state",name:""},
  {etiquette:"load4_energy",name:""},
  {etiquette:"",name:"-------- Configuration onduleur ---------"},
  {etiquette:"ac_temp",name:""},
  {etiquette:"dc_temp",name:""},
  {etiquette:"voltage_state",name:""},
  {etiquette:"frequency_state",name:""},
  {etiquette:"current_state",name:""},
  {etiquette:"",name:""}];
  
/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
});

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} };
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  var registretohex = '{{non valable}}';
  var registre = parseInt(_cmd.configuration.registre);
  if (isNaN(registre)) {
    registre = '{{non valable}}';
  }else {
    var registretohex = '0x' + (registre.toString(16).padStart(4, '0'))
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td class="hidden-xs">';
  tr += '<span class="cmdAttr" data-l1key="id"></span>';
  tr += '</td>';
  tr += '<td>';
  tr += '<div class="input-group">';
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
  tr += '</div>';
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">';
  tr += '<option value="">{{Aucune}}</option>';
  tr += '</select>';
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
  tr += '</td>';
  tr += '<td class="hidden-xs">';
  tr += registre
  tr += '</td>'
  tr += '<td class="hidden-xs">'
  tr += registretohex
  tr += '</td>'
  tr += '<td class="hidden-xs">'
  
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="widget">';
/*
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="widget">'
  liste_donnees.forEach(function(element) {
    if (element.etiquette == "") { 
      tr += '<option value="' + element.etiquette + '">' + element.name + '</option>'
    } else {
      tr += '<option value="' + element.etiquette + '">' + element.etiquette + '</option>'
    }
  });
  tr += '</select>'
  tr += '</span>'
*/  
  tr += '</td>'
  tr += '<td>';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> ';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> ';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> ';
  tr += '<div style="margin-top:7px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '</div>';
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += '</td>';
  tr += '<td>';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>';
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  var tr = $('#table_cmd tbody tr').last();
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' });
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });
}

/* Ajouter un nouvel ECU avec timeout */
$('.eqLogicAction[data-action=addAPSystemsSunspecEq]').off('click').on('click', function () {
  var dialog_message = '<form class="form-horizontal">';
  dialog_message += '<div class="form-group">';
  dialog_message += '<label class="col-sm-3 control-label">{{Nom du nouvel équipement :}}</label>';
  dialog_message += '<div class="col-sm-9">';
  dialog_message += '<input id="eqName" class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" placeholder="{{Nom de l\'ECU}}">';
  dialog_message += '</div>';
  dialog_message += '</div>';
  dialog_message += '<div class="form-group">';
  dialog_message += '<label class="col-sm-3 control-label">{{Adresse IP de l\'ECU :}}</label>';
  dialog_message += '<div class="col-sm-9">';
  dialog_message += '<input id="eqIp" class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" placeholder="{{192.168.1.xxx}}">';
  dialog_message += '</div>';
  dialog_message += '</div>';
  dialog_message += '<div class="form-group">';
  dialog_message += '<label class="col-sm-3 control-label">{{Timeout (secondes) :}}</label>';
  dialog_message += '<div class="col-sm-9">';
  dialog_message += '<input id="eqTimeout" class="bootbox-input bootbox-input-number form-control" autocomplete="off" type="number" min="1" max="30" value="3">';
  dialog_message += '</div>';
  dialog_message += '</div>';
  dialog_message += '</form>';
  bootbox.dialog({
    title: "{{Ajouter un nouvel ECU}}",
    message: dialog_message,
    buttons: {
      cancel: {
        label: "{{Annuler}}",
        className: "btn-default",
        callback: function() {}
      },
      success: {
        label: "{{Sauvegarder}}",
        className: "btn-success",
        callback: function() {
          var name = $('#eqName').val();
          var logicalId = $('#eqIp').val();
          var timeout = $('#eqTimeout').val();

          if (!name || name === '') {
            $.fn.showAlert({ message: "{{Le nom de l'équipement ne peut pas être vide !}}", level: 'warning' });
            return false;
          }
          if (!logicalId || logicalId === '') {
            $.fn.showAlert({ message: "{{Il faut saisir une adresse IP !}}", level: 'warning' });
            return false;
          }
          if (!timeout || timeout < 1 || timeout > 30) {
            $.fn.showAlert({ message: "{{Le timeout doit être entre 1 et 30 secondes !}}", level: 'warning' });
            return false;
          }

          jeedom.eqLogic.save({
            type: 'APSystemsSunspec',
            eqLogics: [{ name: name, logicalId: logicalId, configuration: { timeout: timeout } }],
            error: function (error) {
              $.fn.showAlert({ message: error.message, level: 'danger' });
            },
            success: function (savedEq) {
              $.fn.showAlert({ message: "{{Nouvel ECU créé, page rechargée}}", level: 'success' });
              window.location.reload();
            }
          });
        }
      }
    }
  });
});

/* Gestion du scan et affichage dynamique */
$(document).ready(function() {
  console.log('APSystemsSunspec.js chargé');

  // Vérifier si l'équipement est un équipement fils ou père
  var logicalId = $('.eqLogicAttr[data-l1key="logicalId"]').val();
  console.log('logicalId : ', logicalId); // Débogage
  // Vérifier si le logicalId contient '_ID' pour déterminer s'il s'agit d'un équipement fils
  if (logicalId && logicalId.includes('_ID')) {
    console.log('Équipement fils détecté, masquage des éléments père et affichage des éléments fils');
    $('.ecu-container').hide();
    $('.mo-container').show();
  } else {
    console.log('Équipement père détecté, affichage des éléments père et masquage des éléments fils');
    $('.ecu-container').show();
    $('.mo-container').hide();
  }

  // Charger les données de l'équipement sélectionné
  $('.eqLogicDisplayCard').on('click', function() {
    var eqLogicId = $(this).data('eqlogic_id');
    jeedom.eqLogic.print({
      type: 'APSystemsSunspec',
      id: eqLogicId,
      error: function(error) {
        $('#div_alert').showAlert({ message: error.message, level: 'danger' });
      },
      success: function(data) {
        $('.eqLogicThumbnailDisplay').hide();
        $('.eqLogic').show();
        $('.eqLogicAttr').setValues(data, '.eqLogicAttr');
        // Vider le tableau des commandes avant de le remplir
        $('#table_cmd tbody').empty();
        // Ajouter chaque commande manuellement
        if (data.cmd && Array.isArray(data.cmd)) {
          data.cmd.forEach(function(cmd) {
            addCmdToTable(cmd);
          });
        }
        // Vérifier et ajuster l'affichage du bouton de scan
        checkScanButtonVisibility(data.logicalId);
      }
    });
  });

  $('#scanMicroInverters').on('click', function() {
    console.log('Clic sur Scan des micro-onduleurs');
    var eqLogicId = $('.eqLogicAttr[data-l1key="id"]').val();
    var objectId = $('.eqLogicAttr[data-l1key="object_id"]').val();
    var ifChecked = $('#scanMicroInvertersCheck').is(':checked');
    console.log('Scan des micro-onduleurs, ID : ' + eqLogicId + ', Object ID : ' + objectId + ', Checked : ' + ifChecked);
    if (!eqLogicId) {
      console.log('Erreur : Aucun ID trouvé');
      $('#div_alert').showAlert({ message: '{{Aucun équipement sélectionné}}', level: 'danger' });
      return;
    }
    console.log('Envoi AJAX avec ID : ' + eqLogicId + ' avec Object ID : ' + objectId);
    $.ajax({
      type: 'POST',
      url: 'plugins/APSystemsSunspec/core/ajax/APSystemsSunspec.ajax.php',
      data: {
        action: 'scanMicroInverters',
        id: eqLogicId,
        obj: objectId,
        check: ifChecked
      },
      dataType: 'json',
      error: function(request, status, error) {
        console.log('Erreur AJAX : ' + error);
        $('#div_alert').showAlert({ message: error, level: 'danger' });
      },
      success: function(data) {
        console.log('Réponse AJAX : ' + JSON.stringify(data));
        if (data.state !== 'ok') {
          $('#div_alert').showAlert({ message: data.result, level: 'danger' });
        } else {
          $('#div_alert').showAlert({ message: '{{Scan terminé avec succès, page rechargée}}', level: 'success' });
          window.location.reload();
        }
      }
    });
  });

  $('#refreshToutECU').on('click', function() {
    console.log('Clic sur le bouton de rafraîchissement complet');
    var eqLogicId = $('.eqLogicAttr[data-l1key="id"]').val();
    if (!eqLogicId) {
      console.log('Erreur : Aucun ID trouvé');
      $('#div_alert').showAlert({ message: '{{Aucun équipement sélectionné}}', level: 'danger' });
      return;
    }
    console.log('Envoi AJAX avec ID : ' + eqLogicId);
    $.ajax({
      type: 'POST',
      url: 'plugins/APSystemsSunspec/core/ajax/APSystemsSunspec.ajax.php',
      data: {
        action: 'refreshTout',
        id: eqLogicId
      },
      dataType: 'json',
      error: function(request, status, error) {
        console.log('Erreur AJAX : ' + error);
        $('#div_alert').showAlert({ message: error, level: 'danger' });
      },
      success: function(data) {
        console.log('Réponse AJAX : ' + JSON.stringify(data));
        if (data.state !== 'ok') {
          $('#div_alert').showAlert({ message: data.result, level: 'danger' });
        } else {
          $('#div_alert').showAlert({ message: '{{Rafraîchissement terminé avec succès}}', level: 'success' });
          window.location.reload();
        }
      }
    });
  });


  // Fonction pour vérifier et ajuster la visibilité des élémlents
  function checkScanButtonVisibility(logicalId) {
    console.log('Vérification du logicalId : ', logicalId); // Débogage
    if (logicalId && logicalId.includes('_ID')) {
      console.log('Équipement fils détecté, masquage des éléments père et affichage des éléments fils');
      $('.ecu-container').hide();
      $('.mo-container').show();
    } else {
      console.log('Équipement père détecté, affichage des éléments père et masquage des éléments fils');
      $('.ecu-container').show();
      $('.mo-container').hide();
    }
  }

  // Gestion de l'événement afterEqLogicTabContentLoaded (au cas où)
  $('.eqLogic').on('afterEqLogicTabContentLoaded', function() {
    var logicalId = $('.eqLogicAttr[data-l1key="logicalId"]').val();
    console.log('afterEqLogicTabContentLoaded - logicalId : ', logicalId); // Débogage
    checkScanButtonVisibility(logicalId);
  });
});