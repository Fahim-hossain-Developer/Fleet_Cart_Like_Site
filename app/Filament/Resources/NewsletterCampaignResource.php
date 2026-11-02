<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Actions\QueueNewsletterCampaign;
use App\Domain\Marketing\Models\NewsletterCampaign;
use App\Domain\Marketing\Models\NewsletterMessage;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsletterCampaignResource extends MarketingResource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static ?string $slug = 'newsletter-campaigns';

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name.en')->label(__('marketing.subject').' · English')->required()->maxLength(200), TextInput::make('name.bn')->label(__('marketing.subject').' · বাংলা')->maxLength(200), Textarea::make('body.en')->label(__('content.body').' · English')->required()->maxLength(20000)->rows(8), Textarea::make('body.bn')->label(__('content.body').' · বাংলা')->maxLength(20000)->rows(8), DateTimePicker::make('send_at')->label(__('marketing.send_at'))]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label(__('marketing.subject')), TextColumn::make('status')->label(__('content.status')), TextColumn::make('send_at')->label(__('marketing.send_at'))->dateTime(), TextColumn::make('accepted')->label(__('marketing.transport_accepted'))->state(fn ($record) => NewsletterMessage::where('newsletter_campaign_id', $record->id)->where('status', 'sent')->count()), TextColumn::make('uncertain')->label(__('marketing.uncertain'))->state(fn ($record) => NewsletterMessage::where('newsletter_campaign_id', $record->id)->where('status', 'unknown')->count())])->actions([EditAction::make(), DeleteAction::make(), Action::make('queue')->label(__('marketing.queue_campaign'))->requiresConfirmation()->modalDescription(__('marketing.campaign_consent_help'))->visible(fn ($record) => in_array($record->status, ['draft', 'paused'], true))->action(fn ($record) => app(QueueNewsletterCampaign::class)->execute($record)), Action::make('pause')->label(__('marketing.pause'))->visible(fn ($record) => in_array($record->status, ['queued', 'sending'], true))->action(fn ($record) => app(QueueNewsletterCampaign::class)->pause($record))]);
    }

    public static function getPages(): array
    {
        return ['index' => NewsletterCampaignResource\Pages\ManageRecords::route('/')];
    }
}
