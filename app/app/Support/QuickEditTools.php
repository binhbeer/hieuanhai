<?php

namespace App\Support;

use Illuminate\Support\Str;

class QuickEditTools
{
    /**
     * @return array<string, array{title: string, description: string, overview: string, preserves: string, use_cases: list<string>, related: list<string>, instruction: string, roles: list<string>, source_role: string, caution: string|null, thumbnail?: string, heading?: string, seo_title?: string, seo_description?: string, keywords?: string, cover_alt?: string, content_heading?: string, content_description?: string, content?: list<array{title: string, body: string}>, guide?: list<string>}>
     */
    public static function all(): array
    {
        return [
            'remove-object' => [
                'title' => 'Remove objects',
                'heading' => 'Remove unwanted objects from photos',
                'seo_title' => 'Remove unwanted objects from photos',
                'description' => 'Remove people, text, wires, signs, or distracting objects from a photo and rebuild the hidden background naturally.',
                'seo_description' => 'Remove unwanted objects, people, text, wires, or signs from photos. GenAnh rebuilds the hidden background while keeping the main subject intact.',
                'keywords' => 'remove objects from photos, remove unwanted objects, remove people from photos, remove text from photos, object remover',
                'overview' => 'A passerby can spoil a travel photo. A wire, sign, reflection, or piece of clutter can pull attention away from a product or room. GenAnh removes the area you name and fills it using nearby texture, light, and perspective.',
                'preserves' => 'GenAnh keeps the main subject, crop, perspective, lighting, colors, and details outside the requested area. Only the named person or object should change.',
                'use_cases' => ['Remove passersby from travel photos', 'Clear unwanted signs, wires, or clutter', 'Simplify product and property images'],
                'content_heading' => 'What can you remove from a photo?',
                'content_description' => 'Describe the unwanted detail by type and position, such as "the person behind the subject" or "the wire across the sky". A precise request helps protect nearby edges and textures.',
                'content' => [
                    ['title' => 'People in the background', 'body' => 'Remove passersby, photobombers, or distant figures from travel photos, portraits, and event images without changing the main person.'],
                    ['title' => 'Text, signs, and wires', 'body' => 'Clear signs, loose text, cables, poles, bins, or small distractions when enough surrounding detail remains to rebuild the area.'],
                    ['title' => 'Clutter around products and rooms', 'body' => 'Clean up tables, shelves, property photos, and product scenes while preserving the item, room layout, and camera angle.'],
                ],
                'guide' => [
                    'Point out the object by type, color, and position instead of writing a broad request such as "clean this photo".',
                    'Ask for one clear removal at a time when objects overlap the main subject or complex edges.',
                    'Review hands, hair, text, straight lines, and repeated patterns around the edited area before downloading the result.',
                ],
                'related' => ['replace-background', 'product-photo', 'restore-old-photo'],
                'instruction' => 'Remove only the person or object specified by the user. Preserve the main subject, framing, lighting, perspective, and all unrelated details. Reconstruct the hidden background naturally.',
                'roles' => ['source'],
                'source_role' => 'source',
                'caution' => null,
                'thumbnail' => 'images/tools/remove-object-alt.webp',
                'cover_alt' => 'Example of removing an unwanted object from a photo with GenAnh',
            ],
            'restore-old-photo' => [
                'title' => 'Restore old photos',
                'heading' => 'Restore old photos',
                'seo_title' => 'Restore old photos',
                'description' => 'Repair faded color, scratches, stains, tears, and soft details while keeping faces and the character of the original photo.',
                'seo_description' => 'Restore old photos. Repair scratches, stains, tears, faded color, and soft details while preserving recognizable faces.',
                'keywords' => 'restore old photos, old photo restoration, repair damaged photos, colorize faded photos, photo restoration',
                'overview' => 'Family photos often fade, collect scratches, or lose contrast after years in an album. GenAnh repairs visible damage and balances tone, color, and clarity without turning the image into a modern portrait.',
                'preserves' => 'GenAnh keeps recognizable faces, age, expression, pose, clothing, composition, and period details. Areas with no visible information may remain less precise.',
                'use_cases' => ['Repair scanned family photographs', 'Reduce scratches, stains, and faded color', 'Improve clarity in soft archival images'],
                'content_heading' => 'What damage can photo restoration repair?',
                'content_description' => 'Use the clearest scan or photo available. Avoid strong reflections and crop out the frame when possible, so the damaged image receives most of the detail.',
                'content' => [
                    ['title' => 'Fading and low contrast', 'body' => 'Recover a more balanced range of light and dark areas while keeping the softer look common in older prints.'],
                    ['title' => 'Scratches, stains, and tears', 'body' => 'Reduce surface marks and rebuild small damaged areas using nearby clothing, skin, and background detail.'],
                    ['title' => 'Soft faces and lost color', 'body' => 'Improve facial clarity and color balance without changing age, expression, or recognizable features.'],
                ],
                'guide' => [
                    'Scan the original at the highest practical resolution and keep the print flat to avoid glare and perspective distortion.',
                    'Restore damage first, then request color changes only if the original color is faded or a colorized version is needed.',
                    'Compare faces, clothing, jewelry, and period details with the source before treating reconstructed areas as accurate.',
                ],
                'related' => ['remove-object', 'id-photo', 'replace-background'],
                'instruction' => 'Restore sharpness, tone, color, tears, scratches, stains, and faded areas without changing the identity, age, expression, pose, clothing, or historical character of the source. Do not invent fine details that cannot be supported by the image.',
                'roles' => ['source'],
                'source_role' => 'source',
                'caution' => 'AI may reconstruct details that are no longer visible in heavily damaged areas.',
                'thumbnail' => 'images/tools/restore-old-photo.webp',
                'cover_alt' => 'Example of restoring an old damaged photo with GenAnh',
            ],
            'replace-background' => [
                'title' => 'Replace backgrounds',
                'heading' => 'Replace photo backgrounds',
                'seo_title' => 'Replace photo backgrounds',
                'description' => 'Place a person or product in a new setting while matching edges, perspective, light, shadows, and reflections.',
                'seo_description' => 'Replace photo backgrounds. Keep the person or product intact and match perspective, lighting, edges, and shadows to the new scene.',
                'keywords' => 'replace photo background, change image background, background changer, portrait background, product background',
                'overview' => 'A useful background does more than fill empty space. It must fit the camera angle, depth, light direction, and scale of the subject. GenAnh changes the setting while keeping the person or product recognizable.',
                'preserves' => 'GenAnh keeps the person or product, pose, proportions, clothing, edges, crop, and defining details from the source image.',
                'use_cases' => ['Create cleaner portrait backgrounds', 'Place products in campaign settings', 'Adapt a photo for seasonal content'],
                'content_heading' => 'Choose a background that fits the source photo',
                'content_description' => 'Name the setting and a few useful details, such as time of day, surface, or mood. A compatible camera angle and light direction usually produce a more natural result.',
                'content' => [
                    ['title' => 'Portrait backgrounds', 'body' => 'Move a portrait to an office, studio, outdoor space, or simple color backdrop without changing the face, pose, or clothing.'],
                    ['title' => 'Product settings', 'body' => 'Place a product on a suitable surface or campaign scene while preserving its shape, materials, labels, and logo.'],
                    ['title' => 'Seasonal and campaign scenes', 'body' => 'Adapt an existing photo for a holiday, sale, or visual theme while keeping scale, shadows, and reflections consistent.'],
                ],
                'guide' => [
                    'Describe the location, time of day, light direction, and surface instead of listing decorative objects without context.',
                    'Use a background reference only when its perspective and framing are reasonably close to the source image.',
                    'Check hair edges, transparent materials, feet, contact shadows, and reflections where the subject meets the new scene.',
                ],
                'related' => ['remove-object', 'product-photo', 'id-photo'],
                'instruction' => 'Replace only the background. Preserve the person or main subject identity, pose, body, clothing, product details, and framing. Match perspective, depth, light direction, color temperature, contact shadows, and reflections to the requested setting.',
                'roles' => ['source', 'background', 'identity'],
                'source_role' => 'source',
                'caution' => null,
                'thumbnail' => 'images/tools/replace-background.webp',
                'cover_alt' => 'Example of replacing a photo background with GenAnh',
            ],
            'product-photo' => [
                'title' => 'Create product photos',
                'heading' => 'Create product photos',
                'seo_title' => 'Create product photos',
                'description' => 'Turn product references into clean catalog, marketplace, campaign, or social images without rebuilding a studio setup.',
                'seo_description' => 'Create product photos for catalogs, marketplaces, campaigns, and social posts while preserving shape, materials, colors, labels, and logos.',
                'keywords' => 'product photography, create product photos, product image generator, ecommerce product photos, product background',
                'overview' => 'A sales image should make the product easy to recognize. GenAnh builds a clean studio or campaign scene from reference photos while keeping the item consistent across shape, color, material, labels, and hardware.',
                'preserves' => 'GenAnh keeps product shape, proportions, materials, colors, labels, logos, hardware, and distinctive construction details.',
                'use_cases' => ['Build clean catalog and marketplace images', 'Create campaign-ready product scenes', 'Unify lighting across product content'],
                'content_heading' => 'Product photos for stores and campaigns',
                'content_description' => 'Upload a clear view of the product. Add extra angles only when they show details hidden in the main photo, and state where the image will be used.',
                'content' => [
                    ['title' => 'Catalog and marketplace images', 'body' => 'Create a clean product-first frame with controlled lighting and enough space for common store or marketplace layouts.'],
                    ['title' => 'Campaign scenes', 'body' => 'Place the product in a styled environment suited to its audience while keeping branding and construction accurate.'],
                    ['title' => 'Consistent product series', 'body' => 'Use the same visual direction across several images to keep background, light, and framing more consistent.'],
                ],
                'guide' => [
                    'Use the clearest product view as the main reference and reserve extra images for hidden sides, labels, or material detail.',
                    'State the sales channel, crop, background, lighting, and intended audience in one focused request.',
                    'Verify logo spelling, labels, ports, stitching, hardware, color, and product proportions before publishing the image.',
                ],
                'related' => ['replace-background', 'remove-object', 'add-person'],
                'instruction' => 'Create one professional product image. Preserve the exact product identity, shape, proportions, materials, colors, labels, logo, hardware, and distinctive details. Use supplemental views only to verify the same product. Use style only for visual direction and identity only when the requested person must appear.',
                'roles' => ['product', 'supplemental', 'logo', 'identity', 'style'],
                'source_role' => 'product',
                'caution' => null,
                'thumbnail' => 'images/tools/product-photo.webp',
                'cover_alt' => 'Example of a product photo created with GenAnh',
            ],
            'face-swap' => [
                'title' => 'Swap faces',
                'heading' => 'Swap faces in photos',
                'seo_title' => 'Swap faces in photos',
                'description' => 'Use an authorized identity reference to replace one face while keeping the source pose, clothing, light, and scene.',
                'seo_description' => 'Swap faces in photos using authorized images. Preserve the source pose, clothing, camera angle, lighting, skin texture, and scene.',
                'keywords' => 'face swap, swap faces in photos, face replacement, portrait face swap, authorized face swap',
                'overview' => 'A convincing face swap depends on more than facial features. The new face must fit the source angle, expression, light, skin texture, hairline, and natural overlap with glasses or other objects.',
                'preserves' => 'GenAnh keeps the source pose, body, clothing, scene, camera angle, lighting, skin texture, and natural occlusion around the face.',
                'use_cases' => ['Correct an expression in a personal photo', 'Create authorized creative portraits', 'Adapt a portrait concept with consent'],
                'content_heading' => 'What makes a face swap look natural?',
                'content_description' => 'Use a clear identity photo with a similar angle to the source. Both images must be yours or used with permission.',
                'content' => [
                    ['title' => 'Compatible face angles', 'body' => 'A front-facing identity photo works best with a front-facing source. Similar angles reduce distortion around the jaw, eyes, and hairline.'],
                    ['title' => 'Expression and lighting', 'body' => 'The result keeps the source expression and light direction, so an identity reference with clear facial detail is more useful than a heavily filtered image.'],
                    ['title' => 'Glasses, hair, and overlap', 'body' => 'Frames, hair strands, hands, and other objects crossing the face need clear edges to remain believable.'],
                ],
                'guide' => [
                    'Choose an identity reference with a similar head angle, clear eyes, natural skin texture, and no heavy beauty filter.',
                    'Use only images you own or have permission to use, and keep the result away from fraud, impersonation, or deceptive documents.',
                    'Review the jawline, ears, hairline, eye direction, skin tone, glasses, and objects crossing the face before using the result.',
                ],
                'related' => ['id-photo', 'change-outfit', 'add-person'],
                'instruction' => 'Replace only the face in the source image with the authorized identity reference. Preserve the source pose, body, hair unless requested, clothing, scene, lighting, camera angle, expression compatibility, skin texture, and natural occlusion. Never blend identities or create deceptive documents, fraud, or sensitive impersonation.',
                'roles' => ['source', 'identity'],
                'source_role' => 'source',
                'caution' => 'Only upload images you have permission to use. Do not use results for impersonation, fraud, or sensitive deception.',
                'thumbnail' => 'images/tools/face-swap.webp',
                'cover_alt' => 'Authorized face swap example created with GenAnh',
            ],
            'change-outfit' => [
                'title' => 'Change outfits',
                'heading' => 'Change outfits in photos',
                'seo_title' => 'Change outfits in photos',
                'description' => 'Try different clothing, colors, or dress codes while keeping the same face, body proportions, pose, and scene.',
                'seo_description' => 'Change outfits in photos while preserving the real face, body proportions, pose, expression, hair, background, and lighting.',
                'keywords' => 'outfit changer, change clothes in photo, virtual outfit try on, change clothing color, fashion photo',
                'overview' => 'Changing clothes in a photo requires the new fabric to follow the existing body position. GenAnh fits the requested outfit to the pose, light, and scene without replacing the person.',
                'preserves' => 'GenAnh keeps the real face, identity, body proportions, pose, expression, hair, visible skin, background, and lighting.',
                'use_cases' => ['Preview professional or event outfits', 'Explore campaign wardrobe directions', 'Adapt clothing colors and styles'],
                'content_heading' => 'Describe the outfit you want to try',
                'content_description' => 'Name the garment, color, material, and occasion. A visible upper body or full body gives the tool more information about fit and fabric folds.',
                'content' => [
                    ['title' => 'Work and event clothing', 'body' => 'Preview a blazer, uniform, formal dress, or event outfit while keeping the person and setting unchanged.'],
                    ['title' => 'Colors and materials', 'body' => 'Change color, pattern, or fabric direction without altering the face, pose, or body proportions.'],
                    ['title' => 'Campaign wardrobe', 'body' => 'Test a clothing direction for portraits and marketing images before arranging a full production.'],
                ],
                'guide' => [
                    'Describe garment type, color, material, cut, sleeves, neckline, and occasion, but avoid conflicting style directions.',
                    'Use a source where the relevant part of the body is visible and not heavily blocked by hair, hands, bags, or furniture.',
                    'Check hands, neckline, waist, fabric folds, accessories, and body proportions before accepting the result.',
                ],
                'related' => ['id-photo', 'face-swap', 'replace-background'],
                'instruction' => 'Change only the outfit according to the request or style reference. Preserve the real face, identity, body proportions, pose, expression, hair, visible skin, scene, and lighting. Fit fabric naturally with credible folds and occlusion. Never infer or depict nudity beneath clothing.',
                'roles' => ['identity', 'style', 'source'],
                'source_role' => 'identity',
                'caution' => 'Results must remain clothed and must not infer a nude body beneath the original outfit.',
                'thumbnail' => 'images/tools/change-outfit.webp',
                'cover_alt' => 'Example of changing an outfit in a photo with GenAnh',
            ],
            'add-person' => [
                'title' => 'Add a person',
                'heading' => 'Add a person to a photo',
                'seo_title' => 'Add a person to a photo',
                'description' => 'Place an authorized person into a scene with natural scale, perspective, lighting, shadows, and focus.',
                'seo_description' => 'Add a person to a photo using an authorized identity reference. Match scale, perspective, lighting, shadows, focus, and natural overlap.',
                'keywords' => 'add person to photo, person insertion, add someone to a picture, group photo editor, photo compositing',
                'overview' => 'Adding someone to a group or campaign photo only works when the new person belongs to the scene. GenAnh matches position, scale, camera angle, focus, light, shadow, and overlap with nearby objects.',
                'preserves' => 'GenAnh keeps the original scene and existing people while making the added person recognizable and visually separate.',
                'use_cases' => ['Complete authorized group photographs', 'Visualize a person in a campaign scene', 'Add a presenter to product content'],
                'content_heading' => 'Add someone without disturbing the scene',
                'content_description' => 'State where the person should stand or sit and provide a clear identity image. Mention useful details such as clothing, pose, or distance from the camera.',
                'content' => [
                    ['title' => 'Group photos', 'body' => 'Complete an authorized group image when someone could not attend, while keeping existing people and the original framing.'],
                    ['title' => 'Campaign and social content', 'body' => 'Place a presenter or collaborator in a prepared scene with matching camera angle, light, and depth.'],
                    ['title' => 'Clear identity references', 'body' => 'Use a reference where the face, clothing, and body position are visible enough to place the person without blending identities.'],
                ],
                'guide' => [
                    'Mark the intended position and describe standing, sitting, body direction, clothing, and distance from nearby people.',
                    'Choose a source with enough empty space and an identity reference that clearly shows the authorized person.',
                    'Review feet, contact shadows, eye lines, scale, overlap, reflections, and existing people before using the composite.',
                ],
                'related' => ['face-swap', 'replace-background', 'product-photo'],
                'instruction' => 'Add exactly the authorized identity to the source scene at the user-specified position. Preserve the identity reference without blending features. Match scale, camera perspective, focus, pose, light direction, color, contact shadow, reflections, and occlusion. Do not replace existing people unless explicitly requested.',
                'roles' => ['source', 'identity', 'supplemental'],
                'source_role' => 'source',
                'caution' => 'Only upload images you have permission to use.',
                'thumbnail' => 'images/tools/add-person.webp',
                'cover_alt' => 'Example of adding an authorized person to a photo with GenAnh',
            ],
            'id-photo' => [
                'title' => 'Create profile photos',
                'heading' => 'Create profile and ID-style photos',
                'seo_title' => 'Create profile and ID-style photos',
                'description' => 'Create a clean portrait with even lighting, a simple background, balanced crop, and natural facial detail.',
                'seo_description' => 'Create profile and ID-style photos for directories and non-official records while preserving the real face, age, expression, and skin texture.',
                'keywords' => 'profile photo, ID style photo, professional headshot, profile picture generator, team headshots',
                'overview' => 'A useful profile photo needs a clear face, neutral perspective, even light, and a crop suited to where it will appear. GenAnh prepares a tidy portrait without fabricating credentials or official documents.',
                'preserves' => 'GenAnh keeps the real face, skin texture, expression, age, hair, and recognizable features.',
                'use_cases' => ['Prepare profile and directory portraits', 'Create consistent team headshots', 'Clean up non-official record photos'],
                'content_heading' => 'Profile photos for everyday use',
                'content_description' => 'Start with a sharp photo taken near eye level. State the background color, crop, and clothing only when those details matter for the intended profile.',
                'content' => [
                    ['title' => 'Profiles and directories', 'body' => 'Prepare a clean portrait for internal records, member directories, resumes, and online profiles.'],
                    ['title' => 'Consistent team headshots', 'body' => 'Align background, crop, and lighting across team portraits while keeping each person recognizable.'],
                    ['title' => 'Non-official records', 'body' => 'Clean up a portrait for everyday forms or records that do not require certified identity photography.'],
                ],
                'guide' => [
                    'Use a sharp, eye-level photo with the full face visible, neutral perspective, and even light across both sides of the face.',
                    'State the background color, crop, image ratio, and clothing only when the target profile has a clear requirement.',
                    'Check identity, age, skin texture, hair edges, eye direction, crop, and background before using the portrait.',
                ],
                'related' => ['replace-background', 'change-outfit', 'restore-old-photo'],
                'instruction' => 'Create a clean ID-style portrait using the requested background, crop, size, and sample outfit. Preserve the real face, skin texture, expression, age, hair, and all identifying features. Keep neutral perspective and even lighting. Do not fabricate official documents, visas, stamps, seals, credentials, or certifications.',
                'roles' => ['identity'],
                'source_role' => 'identity',
                'caution' => 'Use for profiles or records only. AI output may not meet official identity-photo requirements.',
                'thumbnail' => 'images/tools/id-photo.webp',
                'cover_alt' => 'Example of a clean profile photo created with GenAnh',
            ],
        ];
    }

    /**
     * @return array{title: string, description: string, overview: string, preserves: string, use_cases: list<string>, related: list<string>, instruction: string, roles: list<string>, source_role: string, caution: string|null, thumbnail?: string, heading?: string, seo_title?: string, seo_description?: string, keywords?: string, cover_alt?: string, content_heading?: string, content_description?: string, content?: list<array{title: string, body: string}>, guide?: list<string>}|null
     */
    public static function get(?string $slug): ?array
    {
        return is_string($slug) ? (self::all()[$slug] ?? null) : null;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return ['source', 'identity', 'product', 'style', 'background', 'logo', 'supplemental'];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(string $slug): array
    {
        $tool = self::get($slug);

        if (! $tool) {
            return [];
        }

        return [
            ['question' => 'What is :tool best used for?', 'answer' => $tool['overview']],
            ['question' => 'What will GenAnh preserve when using :tool?', 'answer' => $tool['preserves']],
            ['question' => 'Can AI recommend a different Quick tool?', 'answer' => 'Yes. GenAnh analyzes the actual image first and may suggest another Quick tool when it is a better fit. The landing content remains unchanged.'],
            ['question' => 'Can I change the AI suggestion?', 'answer' => 'Yes. A suggestion only fills the request field. You can edit or replace it before creating the image.'],
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    public static function contract(string $tool, array $roles, string $request): string
    {
        $config = self::get($tool);

        if (! $config) {
            throw new \InvalidArgumentException('Quick Edit tool is invalid.');
        }

        $descriptions = [
            'source' => 'SOURCE: authoritative image for scene, composition, pose, camera, and elements being edited.',
            'identity' => 'IDENTITY: authoritative person or face identity. Preserve recognizable features; never mix with another identity.',
            'product' => 'PRODUCT: authoritative product or vehicle. Preserve exact shape, construction, color, material, labels, and distinctive details.',
            'style' => 'STYLE: visual style or composition reference only. Never copy or replace a person identity or product identity from this image.',
            'background' => 'BACKGROUND: environment reference only. Never transfer people, products, logos, or identity from this image.',
            'logo' => 'LOGO: branding reference only. Preserve mark, text, colors, and proportions; never treat its canvas as a scene.',
            'supplemental' => 'SUPPLEMENTAL: another view of the same primary subject, used only to verify hidden details; never create a second subject or hybrid.',
        ];
        $lines = [
            'QUICK EDIT REFERENCE ROLE CONTRACT',
            'Images are numbered in upload order. Roles are strict. Never merge, swap, or transfer traits between roles.',
        ];

        foreach ($roles as $index => $role) {
            if (isset($descriptions[$role])) {
                $lines[] = 'Image '.($index + 1).' — '.$descriptions[$role];
            }
        }

        $lines[] = 'Priority: SOURCE controls scene and composition; IDENTITY controls person identity; PRODUCT controls object identity; STYLE controls aesthetics only; BACKGROUND controls environment only; LOGO controls branding only; SUPPLEMENTAL verifies the same subject only.';
        $lines[] = 'Tool contract: '.$config['instruction'];
        $lines[] = 'User request: '.Str::of($request)->squish()->limit(12000, '')->toString();

        return implode("\n", $lines);
    }
}
