<?php 
/** 
 * Save user view
 *
 * Partisk : Political Party Opinion Visualizer
 * Copyright (c) Partisk.nu Team (https://www.partisk.nu)
 *
 * Partisk is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Partisk is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Partisk. If not, see http://www.gnu.org/licenses/.
 *
 * @copyright   Copyright (c) Partisk.nu Team (https://www.partisk.nu)
 * @link        https://www.partisk.nu
 * @package     app.View.Elements
 * @license     http://www.gnu.org/licenses/ GPLv2
 */

if ($canAddUser) { ?>
	<?php

    $roles = $this->requestAction('roles/all');

	$editMode = isset($edit) && $edit;
	$ajaxMode = isset($ajax) && $ajax;

    if (!isset($roleId) && isset($user['User']['role_id'])) {
        $roleId = $user['User']['role_id'];
    }

	?>

    <?php echo $this->Bootstrap->create('User', array('modal' => true, 'label' => $editMode ? "Ändra användare" : "Lägg till användare", 
                    'id' => $editMode ? $user['User']['id'] : null, 'ajax' => $ajaxMode, 'editMode' => $editMode)); ?>
    <?php if ($editMode) { ?>
        <div class="input text form-group">
        <label>Användarnamn</label>
        <p><?php echo $user['User']['username']; ?></p>
        </div>
    <?php } else {
            echo $this->Bootstrap->input('username', array('label' => 'Användarnamn', 'placeholder' => 'Användarens namn', 
                            'value' => null)); 
        }
    ?>
    <?php echo $this->Bootstrap->input('fullname', 
                array('label' => 'Namn' , 'placeholder' => 'För- och Efternamn', 'value' => $editMode ? $user['User']['fullname'] : null)); ?>
    <?php echo $this->Bootstrap->input('email', array('label' => 'E-postadress', 'placeholder' => 'Användarens E-postadress', 'value' => $editMode ? $user['User']['email'] : null)); ?>
    <?php echo $this->Bootstrap->input('password', array('label' => $editMode?'Nytt lösenord':'Lösenord',  'placeholder' => 'Användarens lösenord',
        'type' => 'password')); ?>
    <?php echo $this->Bootstrap->input('confirmPassword', array('label' => $editMode?'Bekräfta nytt lösenord':'Bekräfta lösenord',  'placeholder' => 'Bekräfta användarens lösenord',
        'type' => 'password')); ?>
    <?php if ($canApproveUser && $editMode) {
        echo $this->Bootstrap->checkbox('approved', array('label' => 'Aktiverad', 'type' => 'checkbox',
                    'value' => $editMode ? $user['User']['approved'] : null)); 
    } ?>
    <?php echo $this->Bootstrap->dropdown('role_id', 'Role', array('label' => 'Roll', 'options' => $roles, 
                    'selected' => isset($roleId) ? $roleId : null)); ?>
    <?php echo $this->Bootstrap->textarea('description', array('label' => 'Presentation',  
                    'placeholder' => 'Här kan du skriva in en kort presentation om användaren', 
                    'value' => $editMode ? $user['User']['description'] : null)); ?>
    <?php echo $this->Bootstrap->end($editMode ? "Uppdatera" : "Skapa", array('modal' => true)); ?>
<?php } ?>