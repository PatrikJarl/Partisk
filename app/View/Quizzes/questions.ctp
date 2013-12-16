<?php
/** 
 * Quiz questions view
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
 * @package     app.View.Quiz
 * @license     http://www.gnu.org/licenses/ GPLv2
 */

$this->Html->addCrumb('Quiz', array('controller' => 'quizzes', 'action' => 'index'));
$this->Html->addCrumb('Frågor');

?>

<?php 
echo $this->Form->create('QuizSession', array('url' => '/quizzes/next'));
?>
<h3><?php echo "Fråga " . ($quizSession['QuizSession']['index'] +1) . " av " . 
                           $quizSession['QuizSession']['questions'] . ": " . $question['Question']['title']; ?></h3>
<p><?php echo $question['Question']['description']; ?></p>
<div class="quiz-answers">
<?php
	if ($answer == null) { $answer = 'NO_OPINION'; }

    if ($question['Question']['type'] == 'YESNO') {
        echo $this->Form->input('answer', array('type' => 'radio', 'options' => array('NO_OPINION' => 'ingen åsikt', 'ja' => 'ja', 'nej' => 'nej'), 
    	'default' => 'NO_OPINION', 'legend' => 'Ditt svar', 'value' => $answer, 'separator' => '<div></div>'));
    } else {
        echo $this->Form->input('answer', array('type' => 'radio', 'options' => $choices, 
    	'default' => 'NO_OPINION', 'legend' => 'Ditt svar', 'value' => $answer, 'separator' => '<div></div>'));
    }
?>

<?php 
        echo $this->Form->input('importance', array('type' => 'radio', 'options' => 
                                    array(1 => 'Inte så viktig', 
					  2 => 'Ganska viktigt', 
					  3 => 'Väldigt viktig'), 
                                    'value' => $importance,
                                    'legend' => "Hur viktig är frågan för dig?", 'separator' => '<div></div>'));
?>

</div>
<?php

	if ($quizSession['QuizSession']['index'] != 0) {
		echo $this->Html->link('<i class="fa fa-chevron-left"></i> Föregående',  array('controller' => 'quizzes', 'action' => 'prev'), array('class' => 'btn btn-primary', 'escape' => false));
	} else {
		echo $this->Html->link('<i class="fa fa-chevron-left"></i> Föregående',  array('controller' => 'quizzes', 'action' => 'prev'), array('class' => 'btn btn-primary disabled', 'escape' => false));
    }


	if ($quizSession['QuizSession']['index'] < $quizSession['QuizSession']['questions'] - 1) {
?>
        <button type="submit" class="btn btn-primary"><i class="fa fa-chevron-right"></i> Nästa</button>
<?php
	} else {
?>
        <button type="submit" class="btn btn-primary disabled"><i class="fa fa-chevron-right"></i> Nästa</button>
        <button type="submit" class="btn btn-success"><i class="fa fa-bar-chart-o"></i> Till resultatet</button>
<?php
	}
echo $this->Html->link('<i class="fa fa-times"></i> Avsluta', array('controller' => 'quizzes', 'action' => 'close'), array('class' => 'btn btn-danger', 'escape' => false));

?>

</form>

