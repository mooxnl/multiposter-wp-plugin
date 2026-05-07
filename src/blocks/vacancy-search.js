import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

registerBlockType('multiposter/vacancy-search', {
    title: __('Vacancy Search', 'jobit-vacancies-for-multiposter'),
    icon: 'search',
    category: 'widgets',
    edit: function Edit() {
        const blockProps = useBlockProps();
        return (
            <div {...blockProps}>
                <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                    <strong>{__('Vacancy Search', 'jobit-vacancies-for-multiposter')}</strong>
                    <p>{__('Displays vacancy search filters', 'jobit-vacancies-for-multiposter')}</p>
                </div>
            </div>
        );
    },
    save: () => null,
});
