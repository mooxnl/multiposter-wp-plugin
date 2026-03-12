import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

registerBlockType('multiposter/vacancy-search', {
    title: __('Vacancy Search', 'multiposter'),
    icon: 'search',
    category: 'widgets',
    edit: function Edit() {
        const blockProps = useBlockProps();
        return (
            <div {...blockProps}>
                <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                    <strong>{__('Vacancy Search', 'multiposter')}</strong>
                    <p>{__('Displays vacancy search filters', 'multiposter')}</p>
                </div>
            </div>
        );
    },
    save: () => null,
});
