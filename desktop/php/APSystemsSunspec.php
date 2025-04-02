<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('APSystemsSunspec');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<?php
/**
 * @param string $action_name Nom de l'action
 * @param string $fa_icon Icône FontAwesome
 * @param string $action Action associée
 * @param string $class Classe CSS supplémentaire
 */
function displayActionCard($action_name, $fa_icon, $action = '', $class = '') {
    echo '<div class="eqLogicAction cursor ' . $class . '" data-action="' . $action . '">';
    echo '<i class="fas ' . $fa_icon . '"></i><br/><span>' . $action_name . '</span>';
    echo '</div>' . "\n";
}
?>

<div class="row row-overflow">
    <!-- Page d'accueil du plugin -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <div class="row">
            <div class="col-sm-10">
                <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
                <!-- Boutons de gestion du plugin -->
                <div class="eqLogicThumbnailContainer">
                    <?php
                    displayActionCard('{{Ajouter un ECU}}', 'fa-plus-circle', 'addAPSystemsSunspecEq', 'logoSecondary');
                    displayActionCard('{{Configuration}}', 'fa-wrench', 'gotoPluginConf', 'logoSecondary');
                    ?>
                    <?php
                    $jeedomVersion = jeedom::version() ?? '0';
                    $displayInfoValue = version_compare($jeedomVersion, '4.4.0', '>=');
                    if ($displayInfoValue) {
                    ?>
                        <div class="col-sm-2">
                            <div class="eqLogicThumbnailContainer">
                                <div class="cursor eqLogicAction logoSecondary warning" data-action="createCommunityPost">
                                    <i class="fas fa-ambulance"></i>
                                    <br>
                                    <span class="warning">{{Créer un post Community}}</span>
                                </div>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <legend><i class="fas fa-table"></i> {{Mes ECU}}</legend>
        <?php
        if (count($eqLogics) == 0) {
            echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement trouvé, cliquer sur "Ajouter un ECU" pour commencer}}</div>';
        } else {
            echo '<div class="input-group" style="margin:5px;">';
            echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
            echo '<div class="input-group-btn">';
            echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
            echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
            echo '</div>';
            echo '</div>';
            echo '<div class="eqLogicThumbnailContainer">';
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqlogic-id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $eqLogic->getImage() . '"/>';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '<span class="hiddenAsCard displayTableRight hidden">';
                echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
                echo '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
    </div> <!-- /.eqLogicThumbnailDisplay -->

    <!-- Page de présentation de l'équipement -->
    <div class="col-xs-12 eqLogic" style="display: none;" data-eqlogic-id="">
        <!-- barre de gestion de l'équipement -->
        <div class="input-group pull-right" style="display:inline-flex;">
            <span class="input-group-btn">
                <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
                <a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span></a>
                <a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
                <a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
            </span>
        </div>
        <!-- Onglets -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
        </ul>
        <div class="tab-content">
            <!-- Onglet de configuration de l'équipement -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="col-lg-6">
                            <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Objet parent}}</label>
                                <div class="col-sm-6">
                                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                        <option value="">{{Aucun}}</option>
                                        <?php
                                        $options = '';
                                        foreach ((jeeObject::buildTree(null, false)) as $object) {
                                            $options .= '<option value="' . $object->getId() . '">' . str_repeat('  ', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                        }
                                        echo $options;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                                <div class="col-sm-6">
                                    <?php
                                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                        echo '<label class="checkbox-inline">';
                                        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Options}}</label>
                                <div class="col-sm-6">
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
                                </div>
                            </div>

                            <legend><i class="fas fa-cogs"></i> {{Paramètres spécifiques}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Adresse IP}}</label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="logicalId" readonly/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{ }}</label>
                                <div class="col-sm-6">
                                    <a class="btn btn-primary" id="scanMicroInverters"><i class="fa fa-search"></i> {{Scan des micro-onduleurs}}</a>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Auto-actualisation}}
                                    <sup><i class="fas fa-question-circle tooltips" title="{{Fréquence de rafraîchissement des commandes infos de l'équipement}}"></i></sup>
                                </label>
                                <div class="col-sm-6">
                                    <div class="input-group">
                                        <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="autorefresh" placeholder="{{Cliquer sur ? pour afficher l'assistant cron}}">
                                        <span class="input-group-btn">
                                            <a class="btn btn-default cursor jeeHelper roundedRight" data-helper="cron" title="Assistant cron">
                                                <i class="fas fa-question-circle"></i>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <legend><i class="fas fa-info"></i> {{Informations}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Description}}</label>
                                <div class="col-sm-6">
                                    <textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div><!-- /.tabpanel #eqlogictab-->

            <div role="tabpanel" class="tab-pane" id="commandtab">
                <a class="btn btn-default btn-sm pull-right cmdAction" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a>
                <br><br>
                <div class="table-responsive">
                    <table id="table_cmd" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                                <th>{{Type}}</th>
                                <th style="min-width:260px;">{{Options}}</th>
                                <th>{{Etat}}</th>
                                <th style="min-width:80px;width:200px;">{{Actions}}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div><!-- /.tabpanel #commandtab-->
        </div><!-- /.tab-content -->
    </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<script>
    // Action du bouton de scan
    $('#scanMicroInverters').on('click', function() {
        // Récupère l'ID depuis l'input caché contenant l'ID de l'équipement
        var eqLogicId = $('.eqLogicAttr[data-l1key="id"]').val();
        if (!eqLogicId) {
            $('#div_alert').showAlert({message: '{{Aucun équipement sélectionné ou ID non trouvé}}', level: 'danger'});
            return;
        }
        console.log('ID de l\'équipement envoyé : ' + eqLogicId); // Débogage dans la console
        $.ajax({
            type: 'POST',
            url: 'plugins/APSystemsSunspec/core/ajax/APSystemsSunspec.ajax.php',
            data: {
                action: 'scanMicroInverters',
                id: eqLogicId
            },
            dataType: 'json',
            error: function(request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function(data) {
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                } else {
                    $('#div_alert').showAlert({message: '{{Scan terminé avec succès}}', level: 'success'});
                    jeedom.eqLogic.refreshAll();
                }
            }
        });
    });
</script>

<?php include_file('desktop', 'APSystemsSunspec', 'js', 'APSystemsSunspec'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>