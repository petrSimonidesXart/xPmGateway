<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\PmLookupRepository;
use Nette\Application\UI\Form;

class LookupPresenter extends BasePresenter
{
	public function __construct(
		private PmLookupRepository $lookupRepository,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$all = $this->lookupRepository->getAll();
		$grouped = [];
		foreach ($all as $row) {
			$grouped[$row->category][] = $row;
		}
		$this->template->grouped = $grouped;
		$this->template->categoryNames = [
			'people' => 'Osoby',
			'labels' => 'Štítky',
			'schedule' => 'Plánování',
		];
	}


	public function handleDelete(int $id): void
	{
		$this->requirePost();
		$this->requireAdmin();
		$this->lookupRepository->getTable()->where('id', $id)->delete();
		$this->flashMessage('Záznam smazán.');
		$this->redirect('this');
	}


	protected function createComponentAddForm(): Form
	{
		$form = new Form;
		$form->addSelect('category', 'Kategorie:', [
			'people' => 'Osoby',
			'labels' => 'Štítky',
			'schedule' => 'Plánování',
		])->setRequired();
		$form->addText('shortcut', 'Zkratka:')
			->setRequired('Zadejte zkratku.')
			->setHtmlAttribute('placeholder', 'PS');
		$form->addText('value', 'Hodnota:')
			->setRequired('Zadejte hodnotu.')
			->setHtmlAttribute('placeholder', 'Petr Simonides');
		$form->addText('description', 'Popis:')
			->setNullable()
			->setHtmlAttribute('placeholder', 'Kdy/jak použít');
		$form->addSubmit('send', 'Přidat');
		$form->onSuccess[] = $this->addFormSucceeded(...);
		return $form;
	}


	public function addFormSucceeded(Form $form, \stdClass $values): void
	{
		$this->requireAdmin();

		$existing = $this->lookupRepository->getTable()
			->where('category', $values->category)
			->where('shortcut', strtoupper($values->shortcut))
			->fetch();

		if ($existing) {
			$form->addError("Zkratka {$values->shortcut} už v kategorii {$values->category} existuje.");
			return;
		}

		$maxSort = $this->lookupRepository->getTable()
			->where('category', $values->category)
			->max('sort_order') ?? 0;

		$this->lookupRepository->getTable()->insert([
			'category' => $values->category,
			'shortcut' => strtoupper($values->shortcut),
			'value' => $values->value,
			'description' => $values->description,
			'sort_order' => $maxSort + 1,
		]);

		$this->flashMessage('Záznam přidán.');
		$this->redirect('this');
	}
}
