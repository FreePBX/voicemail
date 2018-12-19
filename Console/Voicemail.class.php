<?php
namespace FreePBX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class Voicemail extends Command{
	protected function configure(){
		$this->setName('voicemail')
		->setDescription(_('Voicemail notification'))
		->addArgument(
			'notification',
			InputArgument::IS_ARRAY,
			'Arguments from voicemail');
		}

		protected function execute(InputInterface $input, OutputInterface $output){
			$options = $input->getArgument('notification');
			if(count($options) !== 3){
				$output->writeln("Incorrect parameter count");
				return false;
			}
			$context = $options[0];
			$extension = $options[1];
			$vmcount = $options[2];
			$this->notification($context,$extension,$vmcount);
		}
		public function notification($context,$extension,$vmcount){
			return \FreePBX::Hooks()->returnHooks($context,$extension,$vmcount);
		}
}
