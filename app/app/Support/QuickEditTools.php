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
                'related' => ['replace-background', 'remove-object', 'advertising-image'],
                'instruction' => 'Create one professional product image. Preserve the exact product identity, shape, proportions, materials, colors, labels, logo, hardware, and distinctive details. Use supplemental views only to verify the same product. Use style only for visual direction and identity only when the requested person must appear.',
                'roles' => ['product', 'supplemental', 'logo', 'identity', 'style'],
                'source_role' => 'product',
                'caution' => null,
                'thumbnail' => 'images/tools/product-photo.webp',
                'cover_alt' => 'Example of a product photo created with GenAnh',
            ],
            'expand-image' => [
                'title' => 'Expand images',
                'heading' => 'Expand images beyond the original frame',
                'seo_title' => 'Expand images beyond the original frame',
                'description' => 'Extend a photo in any direction and generate new surroundings that match its perspective, light, texture, and visual style.',
                'seo_description' => 'Expand images beyond the original frame. GenAnh extends backgrounds and scenery while preserving the original subject, perspective, lighting, and style.',
                'keywords' => 'expand image, extend photo background, AI outpainting, uncrop image, change image aspect ratio',
                'overview' => 'A tight crop can leave no room for a banner, story, or landscape layout. GenAnh extends the canvas around the source and builds believable new scenery from the visible perspective, light, depth, and repeating detail.',
                'preserves' => 'GenAnh keeps every original pixel area, the main subject, camera perspective, scale, light direction, color palette, and visual style. Only the space outside the source frame should be generated.',
                'use_cases' => ['Convert portrait photos into wider layouts', 'Add space for headlines or campaign copy', 'Recover breathing room around tightly cropped subjects'],
                'content_heading' => 'Expand a photo for a new layout',
                'content_description' => 'State the target direction or ratio and describe only essential surroundings. The clearest results come from images with consistent perspective and enough background clues near the edges.',
                'content' => [
                    ['title' => 'Wide banners and landscape crops', 'body' => 'Extend the left and right sides for website headers, presentation covers, and campaign banners without stretching the original subject.'],
                    ['title' => 'Vertical stories and posters', 'body' => 'Generate room above or below a photo for mobile stories, posters, and portrait layouts while continuing the same floor, sky, wall, or scenery.'],
                    ['title' => 'Safer composition around the subject', 'body' => 'Add natural breathing room around a close crop while keeping the person, product, and original camera framing unchanged.'],
                ],
                'guide' => [
                    'Name the direction to extend and the intended aspect ratio, such as wider to 16:9 or taller to 4:5.',
                    'Describe only scenery that should appear outside the frame and avoid requesting changes inside the original image.',
                    'Check repeated patterns, horizon lines, architecture, shadows, and transitions along every original edge.',
                ],
                'related' => ['replace-background', 'enhance-image', 'advertising-image'],
                'instruction' => 'Expand the canvas only outside the original image boundaries. Preserve the complete source image, subject, crop contents, identity, product details, camera perspective, scale, light direction, colors, and style. Continue visible backgrounds and structures naturally into the requested aspect ratio without stretching or duplicating the main subject.',
                'roles' => ['source'],
                'source_role' => 'source',
                'caution' => 'New areas are generated predictions and may not match places or objects that existed outside the original frame.',
                'thumbnail' => 'images/tools/expand-image.webp',
                'cover_alt' => 'Example of expanding a photo beyond its original frame with GenAnh',
            ],
            'enhance-image' => [
                'title' => 'Enhance image quality',
                'heading' => 'Enhance photo quality and detail',
                'seo_title' => 'Enhance photo quality and detail',
                'description' => 'Improve clarity, resolution, texture, color, and noise while keeping the same people, objects, composition, and natural appearance.',
                'seo_description' => 'Enhance image quality with AI. Improve resolution, clarity, texture, color, and noise while preserving identity, composition, and natural detail.',
                'keywords' => 'enhance image quality, AI photo enhancer, upscale image, sharpen photo, reduce image noise',
                'overview' => 'Small, compressed, or slightly soft images can lose useful texture and edge definition. GenAnh improves clarity, balances color, and reduces noise while protecting the visible content and avoiding an artificial over-sharpened look.',
                'preserves' => 'GenAnh keeps identity, age, expression, pose, objects, text, logos, composition, crop, colors, and lighting. Enhancement should not redesign or add visible content.',
                'use_cases' => ['Improve soft or compressed social images', 'Prepare cleaner photos for print and display', 'Reduce noise while retaining natural texture'],
                'content_heading' => 'Improve clarity without changing the photo',
                'content_description' => 'Use the least compressed source available. Enhancement can clarify supported detail, but it cannot recover information that was never captured.',
                'content' => [
                    ['title' => 'Resolution and edge clarity', 'body' => 'Increase usable resolution and make supported edges easier to see without creating halos or a brittle over-sharpened result.'],
                    ['title' => 'Noise and compression cleanup', 'body' => 'Reduce grain, color noise, block artifacts, and banding while keeping skin, fabric, foliage, and material texture believable.'],
                    ['title' => 'Balanced color and contrast', 'body' => 'Correct dull contrast and uneven color gently while preserving the source mood, lighting, and recognizable appearance.'],
                ],
                'guide' => [
                    'Upload the original file instead of a screenshot or repeatedly shared copy whenever possible.',
                    'Mention the main problem, such as noise, softness, compression, or low resolution, instead of asking for every adjustment at once.',
                    'Compare faces, text, logos, fine patterns, and skin texture with the source before using the enhanced image.',
                ],
                'related' => ['restore-old-photo', 'expand-image', 'cinematic-portrait'],
                'instruction' => 'Enhance resolution, clarity, texture, color balance, dynamic range, and noise only where supported by the source. Preserve all people, identity, age, expression, objects, text, logos, composition, crop, geometry, colors, and lighting. Do not add, remove, redesign, beautify, or invent unsupported fine detail.',
                'roles' => ['source'],
                'source_role' => 'source',
                'caution' => 'Very small or heavily compressed sources may require reconstructed detail that is not fully accurate.',
                'thumbnail' => 'images/tools/enhance-image.webp',
                'cover_alt' => 'Example of enhancing photo quality and detail with GenAnh',
            ],
            'advertising-image' => [
                'title' => 'Create advertising images',
                'heading' => 'Create polished advertising images',
                'seo_title' => 'Create polished advertising images',
                'description' => 'Turn product, brand, or portrait references into a focused campaign visual with deliberate composition, lighting, and space for copy.',
                'seo_description' => 'Create advertising images from product, brand, or portrait references. Build polished campaign visuals while preserving products, people, logos, and brand colors.',
                'keywords' => 'advertising image generator, AI ad creative, campaign visual, product advertisement, social media advertising image',
                'overview' => 'A useful ad image needs one clear message, a recognizable subject, and composition that supports the intended channel. GenAnh builds a campaign-ready scene while keeping products, people, logos, and brand colors consistent.',
                'preserves' => 'GenAnh keeps the referenced product or person recognizable, including shape, materials, labels, logos, identity, skin texture, proportions, and approved brand colors.',
                'use_cases' => ['Create social and display campaign visuals', 'Build product launch and promotion concepts', 'Adapt one visual direction across ad formats'],
                'content_heading' => 'Advertising visuals built around one message',
                'content_description' => 'State the product or subject, audience, channel, mood, and copy area. A focused brief produces a stronger hierarchy than a long list of decorative elements.',
                'content' => [
                    ['title' => 'Social and display ads', 'body' => 'Create a strong subject-first visual with a clean focal point and enough negative space for headlines, offers, or a call to action.'],
                    ['title' => 'Product launches and promotions', 'body' => 'Place a referenced product in a campaign scene suited to its audience while preserving construction, labels, color, and brand identity.'],
                    ['title' => 'Channel-ready composition', 'body' => 'Shape the visual for square, portrait, or wide placements while keeping hierarchy and the main subject readable at smaller sizes.'],
                ],
                'guide' => [
                    'State one campaign message, intended audience, publishing channel, aspect ratio, and desired copy area.',
                    'Upload clear product, identity, and logo references only when each one is needed for the final visual.',
                    'Verify product geometry, label and logo spelling, hands, faces, brand colors, and clear space before publishing.',
                ],
                'related' => ['product-photo', 'premium-studio', 'replace-background'],
                'instruction' => 'Create one polished advertising visual around the user request. Preserve referenced product shape, proportions, materials, labels, logos, person identity, anatomy, and approved brand colors. Build clear hierarchy, credible lighting, and deliberate negative space for copy. Do not invent claims, prices, logos, packaging text, or endorsements.',
                'roles' => ['source', 'product', 'logo', 'identity', 'style'],
                'source_role' => 'source',
                'caution' => 'AI-rendered text, labels, prices, and legal claims must be checked and added with a design tool before publishing.',
                'thumbnail' => 'images/tools/advertising-image.webp',
                'cover_alt' => 'Example of an advertising campaign image created with GenAnh',
            ],
            'cinematic-portrait' => [
                'title' => 'Create cinematic portraits',
                'heading' => 'Create cinematic portraits from your photo',
                'seo_title' => 'Create cinematic portraits from your photo',
                'description' => 'Give a portrait cinematic light, color, depth, and atmosphere while preserving the real face, expression, pose, and natural skin texture.',
                'seo_description' => 'Create cinematic portraits from your photo. Add film-inspired lighting, color, depth, and atmosphere while preserving identity and natural facial detail.',
                'keywords' => 'cinematic portrait, AI cinematic photo, film portrait, dramatic portrait lighting, cinematic headshot',
                'overview' => 'Cinematic portraiture uses controlled light, color separation, depth, and framing to create atmosphere. GenAnh applies that visual direction without replacing the person or smoothing away recognizable facial detail.',
                'preserves' => 'GenAnh keeps the real face, age, expression, pose, body proportions, hair, skin texture, clothing, and recognizable features from the identity source.',
                'use_cases' => ['Create dramatic profile and editorial portraits', 'Explore film-inspired lighting and color', 'Add depth and atmosphere to a simple portrait'],
                'content_heading' => 'Build a cinematic mood around the real person',
                'content_description' => 'Name the mood, setting, time of day, and light direction. One coherent film look is more effective than combining many unrelated visual references.',
                'content' => [
                    ['title' => 'Film-inspired lighting', 'body' => 'Shape the portrait with motivated key light, practical glow, rim light, or soft shadow while keeping facial structure and skin natural.'],
                    ['title' => 'Color and atmosphere', 'body' => 'Use a controlled palette, haze, weather, or background depth to support the mood without overwhelming the person.'],
                    ['title' => 'Editorial composition', 'body' => 'Refine crop, foreground, and negative space for a poster-like or editorial frame while maintaining believable camera perspective.'],
                ],
                'guide' => [
                    'Use a clear portrait with visible facial detail and describe one mood, location, and lighting direction.',
                    'Choose a film language such as warm sunset drama or cool urban night instead of naming many conflicting styles.',
                    'Review identity, age, skin texture, eyes, hands, hair edges, and light direction before accepting the result.',
                ],
                'related' => ['premium-studio', 'change-outfit', 'replace-background'],
                'instruction' => 'Create a cinematic portrait using the requested setting, framing, light, color, depth, and atmosphere. Preserve the authorized identity, real face, age, expression, pose, body proportions, hair, skin texture, clothing, and recognizable features. Do not beautify into a different person or copy a protected character.',
                'roles' => ['identity', 'source', 'style'],
                'source_role' => 'identity',
                'caution' => null,
                'thumbnail' => 'images/tools/cinematic-portrait.webp',
                'cover_alt' => 'Example of a cinematic portrait created from a photo with GenAnh',
            ],
            'premium-studio' => [
                'title' => 'Create premium studio images',
                'heading' => 'Create premium studio images',
                'seo_title' => 'Create premium studio images',
                'description' => 'Place a person or product in a refined studio setup with controlled light, premium materials, clean styling, and precise shadows.',
                'seo_description' => 'Create premium studio images for portraits and products. Add refined lighting, materials, styling, and shadows while preserving the original subject.',
                'keywords' => 'premium studio image, luxury product photography, professional studio portrait, AI studio photo, high end studio lighting',
                'overview' => 'Premium studio images depend on restraint: clean surfaces, carefully shaped light, credible materials, and precise contact shadows. GenAnh creates that controlled finish while keeping the original person or product recognizable.',
                'preserves' => 'GenAnh keeps person identity, natural anatomy, product geometry, materials, color, labels, logos, and distinctive details from the source references.',
                'use_cases' => ['Create luxury product hero images', 'Build refined studio portraits', 'Unify a premium visual direction across content'],
                'content_heading' => 'A controlled high-end studio finish',
                'content_description' => 'Describe the subject, surface, backdrop, material palette, and light quality. Fewer intentional elements usually create a more premium result.',
                'content' => [
                    ['title' => 'Luxury product staging', 'body' => 'Present a product on stone, glass, metal, fabric, or a seamless set with controlled reflections and accurate contact shadows.'],
                    ['title' => 'Refined portrait setups', 'body' => 'Create a clean editorial portrait with shaped studio light and premium styling while preserving the real person.'],
                    ['title' => 'Consistent visual systems', 'body' => 'Repeat one backdrop, palette, and lighting direction across related images for a coherent premium collection.'],
                ],
                'guide' => [
                    'Specify the subject, backdrop, surface, two or three materials, color palette, and desired light softness.',
                    'Use clear references with visible edges, accurate color, and enough detail to preserve identity or product construction.',
                    'Check reflections, contact shadows, transparent parts, labels, logos, skin texture, and material transitions.',
                ],
                'related' => ['product-photo', 'advertising-image', 'cinematic-portrait'],
                'instruction' => 'Create a refined premium studio image with deliberate composition, controlled lighting, credible materials, clean styling, precise reflections, and contact shadows. Preserve person identity and anatomy or product shape, proportions, construction, colors, materials, labels, logos, and distinctive details. Keep the set restrained and physically believable.',
                'roles' => ['source', 'product', 'identity', 'logo', 'style'],
                'source_role' => 'source',
                'caution' => null,
                'thumbnail' => 'images/tools/premium-studio.webp',
                'cover_alt' => 'Example of a premium studio image created with GenAnh',
            ],
            'ghibli-style' => [
                'title' => 'Create Ghibli-inspired animation',
                'heading' => 'Turn photos into Ghibli-inspired animation',
                'seo_title' => 'Turn photos into Ghibli-inspired animation',
                'description' => 'Transform a photo into warm hand-painted animation with expressive scenery, soft color, and a whimsical cinematic atmosphere.',
                'seo_description' => 'Turn photos into Ghibli-inspired animation with warm hand-painted color, expressive scenery, and whimsical atmosphere while preserving the source composition.',
                'keywords' => 'Ghibli inspired image, anime photo transformation, hand painted animation, whimsical anime art, animated portrait',
                'overview' => 'Warm hand-painted animation combines simplified shapes, expressive environments, gentle color, and small lived-in details. GenAnh translates the source into that whimsical visual language while keeping its people, composition, and story recognizable.',
                'preserves' => 'GenAnh keeps the number of people, recognizable identity cues, pose, clothing, key objects, composition, camera angle, and emotional tone of the source.',
                'use_cases' => ['Create whimsical animated portraits', 'Transform travel and family photos into illustrations', 'Build original fantasy scenes from personal images'],
                'content_heading' => 'Warm hand-painted animation from your photo',
                'content_description' => 'Describe the mood, season, weather, and important story details. Keep characters and settings original instead of requesting a copy of a film frame.',
                'content' => [
                    ['title' => 'Animated portraits and groups', 'body' => 'Translate people into expressive hand-painted characters while retaining their pose, clothing, relationships, and recognizable cues.'],
                    ['title' => 'Whimsical natural scenery', 'body' => 'Turn streets, gardens, mountains, and travel scenes into layered painted environments with soft atmosphere and lively detail.'],
                    ['title' => 'Original everyday fantasy', 'body' => 'Add a gentle sense of wonder through weather, plants, light, and small environmental details without inserting protected characters.'],
                ],
                'guide' => [
                    'Use a clear source and describe mood, season, weather, palette, and the details that must remain recognizable.',
                    'Request an original hand-painted scene rather than a specific protected character, logo, film frame, or exact artist copy.',
                    'Review faces, hands, group count, clothing, key objects, and composition against the source.',
                ],
                'related' => ['cinematic-portrait', 'premium-studio', 'enhance-image'],
                'instruction' => 'Transform the source into an original warm hand-painted animated scene inspired by whimsical Japanese fantasy cinema. Preserve people count, recognizable identity cues, pose, clothing, key objects, composition, camera angle, and emotional tone. Use soft color, expressive scenery, natural light, and lived-in detail. Do not reproduce protected characters, logos, film frames, or an exact living artist style.',
                'roles' => ['source', 'identity', 'style'],
                'source_role' => 'source',
                'caution' => 'Create original scenes only. Do not reproduce protected characters, logos, film frames, or an exact artist style.',
                'thumbnail' => 'images/tools/ghibli-style.webp',
                'cover_alt' => 'Example of a warm hand-painted animated image created with GenAnh',
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
