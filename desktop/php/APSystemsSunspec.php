<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
// Activer le débogage temporaire
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Déclaration des variables obligatoires
$plugin = plugin::byId('APSystemsSunspec');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

function displayActionCard($action_name, $fa_icon, $action = '', $class = '') {
    echo '<div class="eqLogicAction cursor ' . $class . '" data-action="' . $action . '">';
    echo '<i class="fas ' . $fa_icon . '"></i><br/><span>' . $action_name . '</span>';
    echo '</div>'."\n";
}
?>

<div class="row row-overflow">
    <!-- Page d'accueil du plugin -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <div class="row">
            <div class="col-sm-10">
                <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
                <div class="eqLogicThumbnailContainer">
                    <?php
                    displayActionCard('{{Ajouter un onduleur}}', 'fa-plus-circle', 'addAPSystemsSunspecEq', 'logoSecondary');
                    displayActionCard('{{Configuration}}', 'fa-wrench', 'gotoPluginConf', 'logoSecondary');
                    ?>
                    <?php
                    // À conserver pour la compatibilité 4.4+
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
                <legend><i class="fas fa-table"></i> {{Mes ECU}}</legend>
                <?php
                if (count($eqLogics) == 0) {
                    echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement ECU APSystems trouvé, cliquer sur "Ajouter" pour commencer}}</div>';
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
                        echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
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
            </div>
        </div>
    </div> <!-- /.eqLogicThumbnailDisplay -->

    <!-- Page de présentation de l'équipement -->
    <div class="col-xs-12 eqLogic" style="display: none;">
        <div class="input-group pull-right" style="display:inline-flex;">
            <span class="input-group-btn">
                <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
                <a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span></a>
                <a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
                <a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
            </span>
        </div>
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
        </ul>
        <div class="tab-content">
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
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="logicalId" placeholder="{{Adresse IP de l'ECU}}" readonly />
                                </div>
                            </div>
                            <div class="form-group timeout-container">
                                <label class="col-sm-4 control-label">{{Timeout}}
                                    <sup><i class="fas fa-question-circle tooltips" title="{{A ajuster si vous avez des soucis de connexion}}"></i></sup>
                                </label>
                                <div class="col-sm-6">
                                    <input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="timeout" placeholder="3" min="1" max="30" />
                                </div>
                            </div>
							<div class="form-group autoRefresh-container">
								<label class="col-sm-4 control-label">{{Auto-actualisation}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Fréquence de rafraîchissement des commandes infos des MO}}"></i></sup>
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

                            <div class="form-group">
								<div class="form-group scan-button-container">
									<label class="col-sm-4 control-label">{{Scan pour ajouter les MO de l'ECU}}</label>
									<div class="col-sm-6">
										<a class="btn btn-primary scan-button" id="scanMicroInverters"><i class="fa fa-search"></i> {{Scan des micro-onduleurs}}</a>
									</div>
								</div>
                            </div>
                            <div class="form-group">
								<div class="form-group refresh-tout-container">
									<label class="col-sm-4 control-label">{{MAJ toutes les données ECU}}</label>
									<div class="col-sm-6">
										<a class="btn btn-warning refresh-tout-button" id="refreshToutECU"><i class="fas fa-cogs"></i> {{Refresh tout de l'ECU}}</a>
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
            </div>

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
								<th>{{Registre (en décimal)}}</th>
								<th>{{Registre (en héxadécimal)}}</th>
								<th style="min-width:260px;">{{Options}}</th>
								<th>{{Etat}}</th>
								<th style="min-width:80px;width:200px;">{{Actions}}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_file('desktop', 'APSystemsSunspec', 'js', 'APSystemsSunspec');
include_file('core', 'plugin.template', 'js');
?>