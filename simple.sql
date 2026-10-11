-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2026 at 05:47 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `simple`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `main_image` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `extra_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `blogs`
--

INSERT INTO `blogs` (`id`, `title`, `subtitle`, `author`, `main_image`, `content`, `extra_text`, `created_at`) VALUES
(1, 'WIND SWEPT TREE', 'SLOPE POINT IS THE SOUTHERNMOST TIP OF THE SOUTH ISLAND OF NEW ZEALAND', 'FR-1', 'uploads/blogs/blog_6a665a4cc956f8.15064248.jpg', 'At the southern edge of New Zealand’s South Island lies Slope Point, one of the country’s most exposed coastal landscapes. Facing the vast Southern Ocean, this remote location is renowned for its extraordinary wind-swept trees—trees whose branches and crowns appear to grow almost horizontally, permanently shaped by decades of relentless Antarctic winds.  \r\n\r\nA Landscape Shaped by Wind\r\n\r\nSlope Point sits directly in the path of powerful southwesterly winds that travel uninterrupted across thousands of kilometers of open ocean from Antarctica. With little to block these air currents, the landscape experiences constant exposure to strong, salt-laden winds. Over time, this persistent force influences not only the terrain but also the way vegetation grows.\r\n\r\nRather than growing upward, many trees develop asymmetrical forms. Their trunks remain rooted vertically, but new branches continually grow in the direction of the prevailing wind, creating dramatic silhouettes that seem frozen in motion. This natural phenomenon is an example of how climate and geography can become visible in the form of living organisms.', 'Adaptation Rather Than Resistance\r\n\r\nThe trees at Slope Point illustrate a fundamental principle of survival in nature: adaptation. Instead of resisting the environment, they gradually adjust to it. Their unusual forms are not signs of weakness but evidence of resilience—an ongoing negotiation between biological growth and environmental pressure.\r\n\r\nThis relationship between natural forces and organic form has long inspired photographers, designers, architects, and artists. The trees demonstrate how external conditions can become embedded within material itself, creating forms that could never be intentionally designed.\r\n\r\nNature as a Record of Time\r\n\r\nUnlike dramatic events that happen instantly, the trees of Slope Point are shaped through slow accumulation. Every season of wind leaves an almost imperceptible trace until, over decades, the landscape reveals a visible record of time.\r\n\r\nIn this sense, the trees function as living archives. Their forms document years of weather, climate, and environmental change, making the invisible force of wind permanently legible.', '2026-07-24 19:00:15'),
(2, 'Walking as a Dialogue with Nature', 'Richard Long', 'FR-2', 'uploads/blogs/blog_6a63ca67d7b786.80622152.jpg', 'Richard Long (born 1945) is one of the most influential figures in Land Art and Conceptual Art. Rather than treating nature as a backdrop, Long uses the landscape itself as both his medium and his studio. His artistic practice is remarkably simple: he walks. A path across a mountain, a circle of stones beside a river, or a line traced through grass becomes an artwork that exists in direct relationship with the environment. An exploration of landscape, movement, and the relationship between human presence and nature.\r\n\r\nWalking as an Artistic Process\r\n\r\nLong’s breakthrough work, A Line Made by Walking (1967), was created by repeatedly walking back and forth across a grassy field until a visible line appeared. The work was then documented through a single photograph. The artwork is not merely the photograph—it is the action, the passage of time, and the physical relationship between the artist and the landscape. This work fundamentally expanded the definition of sculpture and introduced walking as a legitimate artistic medium. \r\n\r\nNature as Material, Not an Object\r\n\r\nUnlike many Land Artists who transformed landscapes with heavy machinery, Richard Long works with minimal intervention. He rearranges stones, branches, mud, or leaves that already exist in a place, creating temporary geometric forms such as circles, lines, or spirals. Once nature reclaims these works, they disappear, emphasizing impermanence rather than permanence.  \r\n\r\n\r\nA Line in Japan\r\n\r\nAmong Long’s many walking projects is A Line in Japan, a work produced through a journey across the Japanese landscape. Rather than presenting Japan as a destination, the work records the experience of movement through mountains, forests, rivers, and rural paths. The “line” is not simply a physical mark—it represents distance, time, memory, and the connection between the human body and the environment.\r\n\r\nLike many of Long’s walking works, A Line in Japan exists through documentation: photographs, maps, and text that preserve an otherwise temporary encounter with nature. Recent scholarship highlights how this work creates relationships between places through the act of walking itself.', 'His philosophy is rooted in respect for the landscape. The artwork is created with nature, not against it.\r\n\r\nRichard Long transformed the idea of landscape in contemporary art. Instead of depicting nature in paintings, he entered it directly, allowing walking to become both the creative process and the artwork itself. His practice explores themes including:\r\n\r\n1. The relationship between humans and nature.\r\n2. Time and distance experienced through movement.\r\n3. Ephemeral interventions that leave minimal environmental impact.\r\n4. Documentation as part of the artwork.\r\n\r\nHis work continues to influence artists, designers, architects, and researchers interested in environmental thinking and the relationship between body, place, and landscape.', '2026-07-24 19:18:18'),
(3, 'Sunset on Mars', 'NASA/JPL-Caltech/Cornell University', 'FR-3', 'uploads/blogs/blog_6a64ef5190e0a1.92306674.jpg', 'On May 19, 2005, NASA’s Mars Exploration Rover Spirit captured a remarkable sunset as the Sun dipped below the rim of Gusev Crater on Mars.\r\n\r\nMars is often imagined as a world of endless red deserts and rust-colored horizons. Yet one of its most remarkable natural phenomena appears at the end of the day, when the Sun sinks below the Martian landscape and the sky surrounding it turns blue. Throughout the Martian day, airborne dust scatters sunlight in a way that gives the sky its characteristic butterscotch or reddish appearance. As the Sun approaches the horizon, however, the same dust begins to scatter light differently. Blue wavelengths are directed forward through the atmosphere toward the observer, creating a cool blue glow surrounding the Sun while the rest of the sky remains warm-toned.\r\n\r\nOver the past several decades, NASA has documented this phenomenon through multiple Mars missions, including Viking, Spirit, Opportunity, Curiosity, InSight, and Perseverance. Each mission has captured sunsets under different atmospheric conditions, allowing scientists to study seasonal dust activity, cloud formation, and the structure of the Martian atmosphere.', 'One particularly striking image was captured by the Perseverance rover in Jezero Crater, revealing the delicate blue coloration that has become one of the defining visual characteristics of Martian twilight. Earlier missions, such as Spirit and Opportunity, also recorded extended twilight conditions, demonstrating how fine dust can keep sunlight scattered long after the Sun has disappeared below the horizon.', '2026-07-25 17:16:01'),
(4, 'Walk 2 in Margate', 'Hamish Fulton', 'FR-4', 'uploads/blogs/blog_6a64f6ce2c7d89.27537803.jpg', 'Walk 2 in Margate is a participatory walking artwork by British walking artist Hamish Fulton, created on 3 March 2010 at the Marine Bathing Pool in Margate, England. Rather than producing an object or sculpture, Fulton considers the act of walking itself to be the artwork.\r\n\r\nFor this performance, more than 200 participants walked together in silence, completing seven circuits around the pool while maintaining a consistent distance from one another. Seen from above, the walkers formed a continuous moving line across the landscape. The experience emphasized rhythm, attention, and collective movement, transforming an ordinary walk into a shared moment of reflection.', 'Central to Fulton’s practice is the belief that walking is both a physical and contemplative act. His well-known principle, “No Walk, No Work,” reflects the idea that the experience of movement through a place is the artwork itself. Documentation—whether photographs, maps, or text—serves only as a record of that experience rather than its final form.\r\n\r\nPhoto by Jack Lavender', '2026-07-25 17:47:58'),
(5, 'Devil’s Bridge', 'Worm’s Head Tidal Island, Wales', 'FR-5', 'uploads/blogs/blog_6a665dfa0c88f3.57589093.webp', 'A rocky sea serpent hides caves, blowholes, and the dramatic Devil’s Bridge, a natural rock arch connecting the Inner Head to the Low Neck. Connected to the mainland by a 700-yard (650-meter) tidal causeway, Worm’s Head is only accessible at low tide, offering one of Wales’ most remarkable coastal landscapes shaped by erosion and time.', NULL, '2026-07-26 19:20:26');

-- --------------------------------------------------------

--
-- Table structure for table `blog_gallery`
--

CREATE TABLE `blog_gallery` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `blog_gallery`
--

INSERT INTO `blog_gallery` (`id`, `blog_id`, `image_url`) VALUES
(1, 1, 'uploads/blogs/blog_6a63b63f2b1092.97133668.jpg'),
(2, 1, 'uploads/blogs/blog_6a63b7247d4f95.88089312.webp'),
(3, 1, 'uploads/blogs/blog_6a63b77eb01438.00607548.webp'),
(4, 2, 'uploads/blogs/blog_6a63ba7aee9781.34530402.jpg'),
(5, 2, 'uploads/blogs/blog_6a63ba7aeec6a7.39607687.jpg'),
(6, 2, 'uploads/blogs/blog_6a63ba7aeef261.81098522.jpg'),
(7, 2, 'uploads/blogs/blog_6a63ca67d831f9.87625227.jpg'),
(8, 2, 'uploads/blogs/blog_6a63ca67d870e3.54672151.jpg'),
(10, 1, 'uploads/blogs/blog_6a63cbeeb61a64.99902117.jpg'),
(11, 1, 'uploads/blogs/blog_6a63cbeeb646c0.99089288.jpg'),
(12, 1, 'uploads/blogs/blog_6a63cbeeb672b1.57360687.jpg'),
(13, 1, 'uploads/blogs/blog_6a63cbeeb69f80.05232485.jpg'),
(14, 1, 'uploads/blogs/blog_6a63cbeeb6cbd5.10763641.jpg'),
(15, 1, 'uploads/blogs/blog_6a643de13c1f93.78456438.jpg'),
(18, 1, 'uploads/blogs/blog_6a643de13ceb51.83103498.jpg'),
(27, 3, 'uploads/blogs/blog_6a64ef51912e92.08542257.jpg'),
(28, 3, 'uploads/blogs/blog_6a64ef51915cf9.75024273.jpg'),
(29, 4, 'uploads/blogs/blog_6a64f6ce2ce105.66569656.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('active','checked_out','abandoned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES
(10, 16, 'active', '2026-09-16 12:07:28', '2026-09-16 12:07:28'),
(11, 18, 'active', '2026-09-23 21:15:27', '2026-09-23 21:15:27'),
(12, 19, 'active', '2026-10-03 06:14:28', '2026-10-03 06:14:28');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `cart_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_size_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `has_size` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `has_size`, `created_at`) VALUES
(1, 'new', 'new', 1, '2026-09-10 08:21:13'),
(26, 'Outerwear', 'outerwear', 1, '2026-07-02 18:53:13'),
(27, 'Top', 'top', 1, '2026-07-02 18:54:08'),
(28, 'Bottom', 'Bottom', 1, '2026-07-02 18:54:15'),
(29, 'Accessories', 'accessories', 0, '2026-07-02 18:54:41'),
(30, 'Hat', 'hat', 0, '2026-09-07 16:45:53');

-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `id` int(11) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `year` year(4) NOT NULL,
  `season` enum('Spring','Summer','Autumn','Winter','Fall') NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collection_images`
--

CREATE TABLE `collection_images` (
  `id` int(11) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `is_hero` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hero_slides`
--

CREATE TABLE `hero_slides` (
  `id` int(11) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `button_text` varchar(50) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `image_mobile` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hero_slides`
--

INSERT INTO `hero_slides` (`id`, `title`, `subtitle`, `button_text`, `button_link`, `image`, `image_mobile`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(17, NULL, NULL, NULL, NULL, 'assets/images/hero/hero_6aa26609774894.19123749.png', 'assets/images/hero/hero_6aa0e5c9db4b79.19021543.jpg', 0, 1, '2026-09-09 04:51:21', '2026-09-10 08:10:49'),
(18, NULL, NULL, NULL, NULL, 'assets/images/hero/hero_6aa0e5fbc97695.39793199.png', 'assets/images/hero/hero_6aa0e5fbca8ef8.94646816.jpg', 0, 1, '2026-09-09 04:52:11', '2026-09-09 04:52:11');

-- --------------------------------------------------------

--
-- Table structure for table `kasera_webhook_events`
--

CREATE TABLE `kasera_webhook_events` (
  `event_id` varchar(64) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lookbook_photos`
--

CREATE TABLE `lookbook_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lookbook_year_titles`
--

CREATE TABLE `lookbook_year_titles` (
  `year` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_type` enum('guest','member') NOT NULL DEFAULT 'guest',
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `country_code` varchar(10) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(30) NOT NULL,
  `courier_code` varchar(50) DEFAULT NULL,
  `courier_service` varchar(100) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_provider` varchar(50) DEFAULT NULL,
  `kasera_payment_request_id` varchar(64) DEFAULT NULL,
  `kasera_checkout_url` varchar(2048) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_proof_path` varchar(255) DEFAULT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `payment_verified_by` int(11) DEFAULT NULL,
  `payment_reject_reason` text DEFAULT NULL,
  `invoice_path` varchar(255) DEFAULT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'pending',
  `fulfillment_status` enum('pending','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `kasera_payment_method` varchar(40) DEFAULT NULL,
  `kasera_payment_data` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `customer_type`, `full_name`, `email`, `phone`, `country_code`, `country_name`, `address_line_1`, `address_line_2`, `city`, `state`, `postal_code`, `courier_code`, `courier_service`, `currency`, `subtotal`, `shipping_cost`, `grand_total`, `payment_provider`, `kasera_payment_request_id`, `kasera_checkout_url`, `payment_reference`, `payment_proof_path`, `payment_verified_at`, `payment_verified_by`, `payment_reject_reason`, `invoice_path`, `payment_status`, `fulfillment_status`, `notes`, `created_at`, `updated_at`, `kasera_payment_method`, `kasera_payment_data`) VALUES
(1, 'DEEPDIMES-20260910-316BBE18', NULL, 'guest', 'Arief Rahman', 'mariefrahman223@gmail.com', '+6282354936864', 'ID', 'Indonesia', 'Jl. Rereng Suliga No.5, RT.07/RW.08, Kel. Sukaluyu, Kec. Cibeunying Kaler, Kota Bandung, Jawa Bara', NULL, 'bandung', 'Indonesia (Domestic)', '40123', 'jne', 'CTC', 'IDR', 220000.00, 8000.00, 228000.00, 'kasera_pay', 'payreq_69bd4e1b-bd65-486e-99b9-6f82aba7550f', 'https://pay.kasera.id/p/OFDwj9npGhqJk4YEFy-acIhq6-T1bU3ZsWk03IP3Xkk', NULL, NULL, NULL, NULL, NULL, NULL, 'paid', 'pending', NULL, '2026-09-10 08:07:33', '2026-09-10 08:07:53', NULL, NULL),
(2, 'DEEPDIMES-20260910-5F2C4F3E', NULL, 'guest', 'Azzril ziqrie', 'azzrilziqrie.1@gmail.com', '+62895386847552', 'ID', 'Indonesia', 'dsad', 'sadas', 'cileungsi', 'Indonesia (Domestic)', '16820', 'jne', 'YES', 'IDR', 450000.00, 20000.00, 470000.00, 'kasera_pay', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-10 08:10:18', '2026-09-10 08:10:18', NULL, NULL),
(3, 'DEEPDIMES-20260910-5180993F', NULL, 'guest', 'Azzril ziqrie', 'azzrilziqrie.1@gmail.com', '+62895386847552', 'ID', 'Indonesia', 'dsad', 'sadas', 'cileungsi', 'Indonesia (Domestic)', '16820', 'jne', 'REG', 'IDR', 450000.00, 14000.00, 464000.00, 'kasera_pay', 'payreq_e368de5f-a473-4b2e-9c64-0b89c0a77bb1', 'https://pay.kasera.id/p/wWUaZ9GiskC2PjwnVwtAxaYAspXY6BFbbptx4hMvsq8', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-10 08:12:01', '2026-09-10 08:12:01', NULL, NULL),
(4, 'DEEPDIMES-20260910-BC6D0AB8', NULL, 'guest', 'fakhrullah fadillah', 'zeroviolence801@gmail.com', '+601139697273', '108', 'Malaysia', '26, jalan ariffin 1, yahya awal 80100, johor bahru, johor', NULL, 'johor bahru', NULL, '80100', 'pos', 'POS EKSPOR', 'IDR', 450000.00, 429675.00, 879675.00, 'kasera_pay', 'payreq_1da16e1f-5af4-4e04-bf2a-80eb112f2310', 'https://pay.kasera.id/p/Ke1v36zDW_aTih3GES6I-58llT4aw4XrP4jLF2K5qAg', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-10 13:52:45', '2026-09-10 13:52:45', NULL, NULL),
(5, 'DEEPDIMES-20260911-9DB694B5', NULL, 'guest', 'Isyraq Wajdi', 'isyraq888@gmail.com', '0193133064', '108', 'Malaysia', 'no 27 Taman Setia Jalan Rancangan', 'Kg Jenjarom , Kuala Langat , Selangor', 'Jenjarom', 'Selangor', '42600', 'pos', 'PAKETPOS BIASA LN', 'IDR', 450000.00, 212209.00, 662209.00, 'kasera_pay', 'payreq_d101c949-dca6-41af-afef-6c1fc410ae1a', 'https://pay.kasera.id/p/HB1lgVolVnqwgR84AL59j0-9vRSX-UpCnSTRpAl5Il4', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-11 00:45:09', '2026-09-11 00:45:10', NULL, NULL),
(6, 'DEEPDIMES-20260911-14A83061', NULL, 'guest', 'Azzril ziqrie', 'azzrilziqrie.1@gmail.com', '+62895386847552', '152', 'Singapore', 'dsad', 'sadas', 'cileungsi', 'Indonesia (Domestic)', '16820', 'jne', 'INTL Service', 'IDR', 450000.00, 147000.00, 597000.00, 'kasera_pay', 'payreq_8dc36969-1237-40dd-a12d-b036a290fa1f', 'https://pay.kasera.id/p/Ux_g4C6i8FbHgxEGDiQ3IX4VECahSoDSwNIeH491a78', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-11 11:11:35', '2026-09-11 11:11:35', NULL, NULL),
(7, 'DEEPDIMES-20260913-1D26FAA8', NULL, 'guest', 'Elfy Danzly Bin Raymee', 'elfydanzly08@gmail.com', '+6588547597', '152', 'Singapore', 'Tampines Street 45', 'Blk 498D,#02-384', 'Singapore', 'Singapore', '522498', 'jne', 'INTL Service', 'USD', 20.00, 9.19, 29.19, 'kasera_pay', 'payreq_53c50091-4c33-4e04-9c9f-7edce301e1a7', 'https://pay.kasera.id/p/thPSj2FCYRAntITz_Femv1aTbQU1JDMDCm4TcwwmqC8', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-13 01:13:16', '2026-09-13 01:13:16', NULL, NULL),
(8, 'DEEPDIMES-20260915-321033DC', NULL, 'guest', 'fakhrullah fadillah', 'zeroviolence801@gmail.com', '+601139697273', '108', 'Malaysia', '26 jalan ariffin 1, yahya awal', 'no 26', 'johor bahru', 'johor', '80100', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_5bef0ddb-3715-4b97-8f87-eb9c3b7b2e31', 'https://pay.kasera.id/p/nWu9mxdYSVtqwdwUpvd2Fnw1ri69JBiMIwG5roCw9ss', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-14 23:48:53', '2026-09-14 23:48:53', NULL, NULL),
(9, 'DEEPDIMES-20260915-AB6EC3EC', NULL, 'guest', 'Wayne Nengomasha', 'wayneano@icloud.com', '0451607109', '9', 'Australia', '18 Adriana Crescent', '18 Adriana crescent', 'Warrnambool', 'Victoria', '3280', 'pos', 'PAKETPOS BIASA LN', 'IDR', 350000.00, 281968.00, 631968.00, 'kasera_pay', 'payreq_0cb3ca92-4483-42c4-9823-16811bd58b6a', 'https://pay.kasera.id/p/-q5RDppf4asVqMegy9Vy5v1n-NDJ8HEpKBcmBlHFX7w', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', 'Ship to Australia 18 Adriana crescent Dennington (Warrnambool) 3280', '2026-09-15 09:12:16', '2026-09-15 09:12:18', NULL, NULL),
(10, 'DEEPDIMES-20260915-DB237732', NULL, 'guest', 'Sachin Skye Gunasegaran', 'sachinskye11@gmail.com', '+6594505821', '152', 'Singapore', 'Serangoon North Ave 3', 'level 3, #03-156', 'Singapore', NULL, '550547', 'jne', 'INTL Service', 'IDR', 220000.00, 147000.00, 367000.00, 'kasera_pay', 'payreq_936bcfc3-8fba-4ace-83cf-7f7059f61c08', 'https://pay.kasera.id/p/wwMvAXGWPUR5Z5D_rg6iNJXAaq4sARCGTq9kPmCvOfY', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-15 09:31:11', '2026-09-15 09:31:11', NULL, NULL),
(11, 'DEEPDIMES-20260915-E63D42C2', NULL, 'guest', 'Brent Michael T. De Lemos', 'delemosbrent@gmail.com', '09194601888', '139', 'Philippines', 'Block 4 Lot 2B Sheryl st., Donnaville subd., BFRV. Brgy. Talon 2, Las Piñas city.', NULL, 'Las Piñas City', 'Metro Manila', '1740', 'jne', 'INTL Service', 'IDR', 350000.00, 286000.00, 636000.00, 'kasera_pay', 'payreq_a3bee538-b355-4684-9492-f61f754ea3df', 'https://pay.kasera.id/p/mSN3EQv2rBK7eEL2FFjTmx4ydeybNckvhIVQh7gBZog', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-15 12:04:33', '2026-09-15 12:04:33', NULL, NULL),
(12, 'DEEPDIMES-20260916-1071180D', NULL, 'guest', 'Muhammad akmal', 'pubgloverv2@gmail.com', '+6596817470', '152', 'Singapore', 'block 223 pending road #04-111', NULL, 'singapore', NULL, '670223', 'jne', 'INTL Service', 'IDR', 670000.00, 147000.00, 817000.00, 'kasera_pay', 'payreq_c12d36d4-7ba7-4b1a-bc10-fdd353b35314', 'https://pay.kasera.id/p/b2YJsW85fO_EPhgbLHzPbD_nrjryIAR0NRIGa2Vk2kE', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-15 20:23:50', '2026-09-15 20:23:50', NULL, NULL),
(13, 'DEEPDIMES-20260916-955B2C87', NULL, 'guest', 'Muhammad akmal', 'pubgloverv2@gmail.com', '+6596817470', '152', 'Singapore', 'block 223 pending road #04-111', NULL, 'singapore', NULL, '670223', 'jne', 'INTL Service', 'IDR', 670000.00, 147000.00, 817000.00, 'kasera_pay', 'payreq_583cbbd2-4372-4cb3-9ca5-93a810be886a', 'https://pay.kasera.id/p/8Be_14h9c9n2jvXXemZYtmmlpbJwlhA_a9RqSiasByM', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-15 20:25:35', '2026-09-15 20:25:35', NULL, NULL),
(14, 'DEEPDIMES-20260916-DBA232A1', NULL, 'guest', 'Muhammad akmal', 'pubgloverv2@gmail.com', '+6596817470', '152', 'Singapore', 'block 223 pending road #04-111', NULL, 'singapore', NULL, '670223', 'jne', 'INTL Service', 'IDR', 670000.00, 147000.00, 817000.00, 'kasera_pay', 'payreq_e5a21304-0511-4519-9b6d-041f393f9f33', 'https://pay.kasera.id/p/L93CjkxTxbA5LYGNMNbisGecJEf5hf4fqy-NMqGoJ3c', NULL, NULL, NULL, NULL, NULL, NULL, 'paid', 'pending', NULL, '2026-09-15 20:28:13', '2026-09-15 20:38:03', NULL, NULL),
(15, 'DEEPDIMES-20260916-3EA0E806', NULL, 'guest', 'razlan fuad', 'lanfuad@gmail.com', '+60183855012', '108', 'Malaysia', '1, persiaran wira jaya barat 56 taman rapat setia baru 31350', NULL, 'ipoh', 'perak', '31350', 'jne', 'INTL Service', 'IDR', 900000.00, 106000.00, 1006000.00, 'kasera_pay', 'payreq_58299361-2e6f-42e9-bd04-c3864a96ea9e', 'https://pay.kasera.id/p/KWXlcaVoIwfjsldopHAwBeq4uwyFV9xZdTT9uXicvWc', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-15 20:31:54', '2026-09-15 20:31:55', NULL, NULL),
(16, 'DEEPDIMES-20260916-AE849437', NULL, 'guest', 'razlan fuad', 'lanfuad@gmail.com', '+60183855012', '108', 'Malaysia', '1, persiaran wira jaya barat 56 taman rapat setia baru 31350 ipoh, perak', NULL, 'ipoh', 'perak', '31350', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_adcc27dc-b44c-461d-ac8c-37cf2b3cd3ab', 'https://pay.kasera.id/p/l8Rs_DRwo5oXyxt53svFvjKJcKkN8Ev8ifV_maFBMjg', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-15 20:36:47', '2026-09-15 20:36:47', NULL, NULL),
(17, 'DEEPDIMES-20260916-79297924', NULL, 'guest', 'Raul', 'raulrudy21@gmail.com', '+60197901806', '108', 'Malaysia', 'No.6 Jalan MS6', 'Taman Myra Saujana,Sungai Merab', 'Kajang', 'Selangor', '43000', 'jne', 'INTL Service', 'IDR', 800000.00, 106000.00, 906000.00, 'kasera_pay', 'payreq_cc60ee3d-670b-4dab-bf5e-2a8a68b05f7c', 'https://pay.kasera.id/p/Iuie2ZiLVykkm9y6Vssgx2fe7yf2YZuMRS90PZhf3xQ', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-16 03:08:22', '2026-09-16 03:08:23', NULL, NULL),
(18, 'DEEPDIMES-20260916-DBCFF275', NULL, 'guest', 'MOHAMAD AQASHA RAZIF BIN ROSLIE', 'aqasharazif@gmail.com', '01151655383', '108', 'Malaysia', 'C-3-03 BLOCK C TANGGA 2, JALAN PINGGIRAN DELIMA 2/1', 'TAMAN PINGGIRAN DELIMA, HULU LANGAT SELANGOR', 'HULU PANGAT', 'SELANGOR', '43100', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_2670d28e-0372-4a35-b16b-7b804c954cbf', 'https://pay.kasera.id/p/81pxv3IBqwRhnYl-qzZOvZpo3rSC1CU7CtrkcXRMwTo', NULL, NULL, NULL, NULL, NULL, NULL, 'paid', 'pending', NULL, '2026-09-16 07:23:59', '2026-09-16 07:25:17', NULL, NULL),
(19, 'DEEPDIMES-20260917-B6D6281B', NULL, 'guest', 'Iskandar Zulqarnain', 'iskandarzulqarnain17@gmail.com', '60-103592780', '108', 'Malaysia', 'Tenpower Malaysia', 'Jalan Neutron, Bandar Mahkota Banting', 'Banting', 'Selangor Darul Ehsan', '60', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_96ac2f05-d00a-4641-a78a-8b2cad638f73', 'https://pay.kasera.id/p/NPKQbumaEph9MiCmIHu-CyP5CZV1YLA7DJyDSm6dez4', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-17 02:07:18', '2026-09-17 02:07:19', NULL, NULL),
(20, 'DEEPDIMES-20260917-4B057C36', NULL, 'guest', 'razlan fuad', 'lanfuad@gmail.com', '0183855012', '108', 'Malaysia', '1, persiaran wira jaya barat 56 taman rapat setia baru 31350 ipoh, perak', NULL, 'ipoh', 'perak', '31350', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_13558e6b-baae-495c-ad72-4299febf074c', 'https://pay.kasera.id/p/QmfIaFHlI5gTqbCB1u6Z4sD_EhqbU9fmG6bqQjoEsrY', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-17 15:14:52', '2026-09-17 15:14:52', NULL, NULL),
(21, 'DEEPDIMES-20260918-38F915A9', NULL, 'guest', 'Josh Morris', 'joshuamorris20009@gmail.com', '0493068046', '9', 'Australia', '6 lois court golden square', NULL, 'Bendigo', 'Victoria', '3555', 'jne', 'INTL Service', 'IDR', 350000.00, 403000.00, 753000.00, 'kasera_pay', 'payreq_cc984d95-bef7-4d6a-ae20-f8096eefae45', 'https://pay.kasera.id/p/K7pohBLf7s8HlFAyzZDXzEtH7UHjUl-HMOhoJ4QZYoI', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-18 14:16:04', '2026-09-18 14:16:04', NULL, NULL),
(22, 'DEEPDIMES-20260920-3C839702', NULL, 'guest', 'Tengku Adam Asadel', 'adamasadel1011@gmail.com', '+6588251733', '152', 'Singapore', 'woodlands drive 16,580,06-592', NULL, 'singapore', NULL, '730580', 'jne', 'INTL Service', 'IDR', 350000.00, 147000.00, 497000.00, 'kasera_pay', 'payreq_e9291d83-4968-4679-b1d9-0be65facb53f', 'https://pay.kasera.id/p/lKyd3GcejSFipHhnSaGk1_lCfpek_5iFyP_REAQq6jQ', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-20 11:22:28', '2026-09-20 11:22:29', NULL, NULL),
(23, 'DEEPDIMES-20260920-09DC4C6E', NULL, 'guest', 'widy elvan', 'widit4134@gmail.com', '0881024078604', 'ID', 'Indonesia', 'Kp. Rawajamun Bapak Wagiman Ibu Mumun (Gang Nangka) RT04/RW04 DS. Dayeuh Kec. Cileungsi Kab. bogor', NULL, 'Cileungsi Bogor', 'Jawa Barat', '12345', 'jne', 'REG', 'IDR', 450000.00, 14000.00, 464000.00, 'kasera_pay', 'payreq_acb7e772-b6ed-4e60-be30-7bb18ef7ed25', 'https://pay.kasera.id/p/Gz08u3LTGzd75jg9BdSAQZAs4Wxfb7mCjP4lkJIub9U', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-20 15:20:06', '2026-09-20 15:20:06', NULL, NULL),
(24, 'DEEPDIMES-20260923-FC8506E2', NULL, 'guest', 'Muhammad Affandi', 'Fendihakim020@gmail.com', '0102911921', '108', 'Malaysia', 'DT1049, Jalan Bukit Tambun Perdana 27, Taman Bukit Tambun Perdana, 76100', NULL, 'Melaka', 'Alorgajah', '76100', 'jne', 'INTL Service', 'IDR', 635000.00, 106000.00, 741000.00, 'kasera_pay', 'payreq_af09a343-840f-4fc3-a86e-4a2befed1aee', 'https://pay.kasera.id/p/5i-zIczTdIwvPX7zg-B7jUEnTmHTVG0j6lnywaFmFQk', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-22 18:46:48', '2026-09-22 18:46:48', NULL, NULL),
(25, 'DEEPDIMES-20260923-E6A43077', NULL, 'guest', 'Muhammad Affandi', 'Fendihakim020@gmail.com', '0102911921', '108', 'Malaysia', 'DT1049, Jalan Bukit Tambun Perdana 27, Taman Bukit Tambun Perdana, 76100', NULL, 'Melaka', 'Alorgajah', '76100', 'jne', 'INTL Service', 'IDR', 635000.00, 106000.00, 741000.00, 'kasera_pay', 'payreq_b1b28b79-5308-4964-bcd1-caebd8c1584f', 'https://pay.kasera.id/p/0CObXZE-HMpRe29Qbf557qN2d443hojJ4ZJx2dIgpPI', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-22 18:54:40', '2026-09-22 18:54:41', NULL, NULL),
(26, 'DEEPDIMES-20260923-2D5981A2', NULL, 'guest', 'emerul miqail', 'emerulmiqail05@gmail.com', '0135949601', '108', 'Malaysia', '138, Jalan Ttjs 3/6', 'taman tuanku jaafar', 'Seremban', 'Negeri Sembilan', '71450', 'jne', 'INTL Service', 'IDR', 350000.00, 106000.00, 456000.00, 'kasera_pay', 'payreq_9cc66f55-d423-48d5-b720-a101dc59e1fd', 'https://pay.kasera.id/p/jNiYMw7Fn5jEAx_ViN5EKZ18CeOkz_7qHddFjCqU-YY', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-23 04:48:10', '2026-09-23 04:48:10', NULL, NULL),
(27, 'DEEPDIMES-20260923-30D90E81', NULL, 'guest', 'Muhammad Syukri Bin Hussin', 'muhds5601@icloud.com', '60194642640', '108', 'Malaysia', '15, Jalan SJ 1/2', NULL, 'Malacca', 'Malacca', '75350', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_4e111d69-ed3f-4980-af32-c8b5a6d84734', 'https://pay.kasera.id/p/vGzgx9d4i8TIbJ48CoRN3ZpB16R3u2DXA6HZkjdhUws', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-23 10:20:45', '2026-09-23 10:20:45', NULL, NULL),
(28, 'DEEPDIMES-20260925-AF28A57E', NULL, 'guest', 'Roy Oconnell', 'royoconnell123@gmail.com', '+61492184695', '9', 'Australia', '33 Perry Street', NULL, 'Williamstown', 'VIC', '3016', 'jne', 'INTL Service', 'IDR', 350000.00, 403000.00, 753000.00, 'kasera_pay', 'payreq_6c7df00e-6f24-4d8a-9293-6c4b5646eae7', 'https://pay.kasera.id/p/w4DTN9eU25jn2_8loy6sKgjxDaYkCTOgC2A3U9jXeik', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-24 21:34:06', '2026-09-24 21:34:06', NULL, NULL),
(29, 'DEEPDIMES-20260925-A72467B4', NULL, 'guest', 'James Camilleri', 'jamescamilleri56@gmail.com', '0404437233', '9', 'Australia', '11 Orietta Court', NULL, 'Angle Vale', 'South Australia', '5117', 'jne', 'INTL Service', 'IDR', 350000.00, 403000.00, 753000.00, 'kasera_pay', 'payreq_13225622-0c21-41b7-9795-c9dfb99121fa', 'https://pay.kasera.id/p/gDu_aTPqquRUPeywOGZiYfv-lQo-8yMPdfYXq0CN2bE', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-25 04:45:07', '2026-09-25 04:45:07', NULL, NULL),
(30, 'DEEPDIMES-20260927-B1DCD05E', NULL, 'guest', 'fakhrullah fadillah', 'zeroviolence801@gmail.com', '+601139697273', '108', 'Malaysia', '26, jalan ariffin 1, yahya awal 80100, johor bahru, johor, malaysia', 'no 26', 'Johor Bahru', NULL, '80100', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_84517a3c-3317-4e69-851d-d0f805a025e1', 'https://pay.kasera.id/p/yUbszAYwjNo-MZJKnScDT59Yj_SPaTf2VbuhDzuSeRc', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-09-27 02:00:25', '2026-09-27 02:00:25', NULL, NULL),
(31, 'DEEPDIMES-20260930-697EC31E', NULL, 'guest', 'fakhrullah fadillah', 'zeroviolence801@gmail.com', '+601139697273', '108', 'Malaysia', '26, jalan ariffin 1, yahya awal 80100, johor bahru, johor, malaysia', NULL, 'johor bahru', 'johor', '80100', 'jne', 'INTL Service', 'IDR', 450000.00, 106000.00, 556000.00, 'kasera_pay', 'payreq_ade4265a-e242-407d-b8c7-3f10336b36ff', 'https://pay.kasera.id/p/xWFMBQR0fIU-AJjHUq9oIy8c4p3T0XRTIOHITio9SZ4', NULL, NULL, NULL, NULL, NULL, NULL, 'paid', 'pending', NULL, '2026-09-30 10:42:52', '2026-09-30 10:43:55', NULL, NULL),
(32, 'DEEPDIMES-20261002-309EDAAE', NULL, 'guest', 'MUHAMMAD LUKMAN HAKIM', 'mhmdlukmanhkm@gmail.com', '082141263703', 'ID', 'Indonesia', 'Jalan Karang Rejo Timur III No. 9', NULL, 'Kota Surabaya', 'Jawa Timur', '60243', 'jne', 'REG', 'IDR', 450000.00, 21000.00, 471000.00, 'kasera_pay', 'payreq_0c9ea8cc-47cc-4f31-9ee1-c953bb40d44e', 'https://pay.kasera.id/p/k9I6fULOatx_2wVrBX90C_R9NdiiuYy3stSYAavsPfI', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-10-02 15:41:42', '2026-10-02 15:41:43', NULL, NULL),
(33, 'DEEPDIMES-20261004-A8362625', NULL, 'guest', 'Muhammad Lukman Hakim', 'mhmdlukmanhkm@gmail.com', '082141263703', 'ID', 'Indonesia', 'JL. Karangrejo Timur 3 No. 9', NULL, 'SURABAYA', 'JAWA TIMUR', '60243', 'jne', 'REG', 'IDR', 450000.00, 21000.00, 471000.00, 'kasera_pay', 'payreq_5742bc14-046e-4c57-ae2d-c43c51598355', 'https://pay.kasera.id/p/xF5KFtOoD1B7k4TqQgpH2hjwIT4eD5xPMhYxJLPq0ko', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', 'SIZE MEDIUM', '2026-10-04 04:35:33', '2026-10-04 04:35:34', NULL, NULL),
(34, 'DEEPDIMES-20261006-AAD26025', NULL, 'guest', 'Azzril ziqrie', 'azzrilziqrie.1@gmail.com', '+62895386847552', 'ID', 'Indonesia', 'dsad', NULL, 'cileungsi', NULL, '16820', 'jne', 'REG', 'IDR', 450000.00, 14000.00, 464000.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, '2026-10-06 01:23:02', '2026-10-06 01:23:02', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_size_id` int(10) UNSIGNED DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_slug` varchar(255) NOT NULL,
  `size_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `thumbnail` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_size_id`, `product_name`, `product_slug`, `size_name`, `quantity`, `price`, `line_total`, `thumbnail`, `created_at`) VALUES
(1, 1, 43, NULL, 'SUNZ TEE', 'sunz-tee', 'Large', 1, 220000.00, 220000.00, 'assets/images/products/product_6aa10fb6d22df0.30283944.webp', '2026-09-10 08:07:33'),
(2, 2, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-10 08:10:18'),
(3, 3, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-10 08:12:01'),
(4, 4, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'X-Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-10 13:52:45'),
(5, 5, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'Medium', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-11 00:45:09'),
(6, 6, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-11 11:11:35'),
(7, 7, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Medium', 1, 20.00, 20.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-13 01:13:16'),
(8, 8, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'X-Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-14 23:48:53'),
(9, 9, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-15 09:12:16'),
(10, 10, 40, NULL, 'LOCKED IN TEE', 'locked-in-tee', 'Medium', 1, 220000.00, 220000.00, 'assets/images/products/product_6aa10d8516f1b4.85412172.webp', '2026-09-15 09:31:11'),
(11, 11, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-15 12:04:33'),
(12, 12, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-15 20:23:50'),
(13, 12, 40, NULL, 'LOCKED IN TEE', 'locked-in-tee', 'Large', 1, 220000.00, 220000.00, 'assets/images/products/product_6aa10d8516f1b4.85412172.webp', '2026-09-15 20:23:50'),
(14, 13, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-15 20:25:35'),
(15, 13, 40, NULL, 'LOCKED IN TEE', 'locked-in-tee', 'Large', 1, 220000.00, 220000.00, 'assets/images/products/product_6aa10d8516f1b4.85412172.webp', '2026-09-15 20:25:35'),
(16, 14, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-15 20:28:13'),
(17, 14, 40, NULL, 'LOCKED IN TEE', 'locked-in-tee', 'Large', 1, 220000.00, 220000.00, 'assets/images/products/product_6aa10d8516f1b4.85412172.webp', '2026-09-15 20:28:13'),
(18, 15, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'Medium', 2, 450000.00, 900000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-15 20:31:54'),
(19, 16, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'Medium', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-15 20:36:47'),
(20, 17, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-16 03:08:22'),
(21, 17, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Large', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-16 03:08:22'),
(22, 18, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'X-Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-16 07:23:59'),
(23, 19, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Medium', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-17 02:07:18'),
(24, 20, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'Medium', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-17 15:14:52'),
(25, 21, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Large', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-18 14:16:04'),
(26, 22, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-20 11:22:28'),
(27, 23, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Small', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-20 15:20:06'),
(28, 24, 46, NULL, 'MINO PLAID SHORTS', 'mino-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa107ffd371e0.73412850.jpg', '2026-09-22 18:46:48'),
(29, 24, 39, NULL, 'MONOSTAR DOUBLE LAYER TEE', 'monostar-double-layer-tee', 'Medium', 1, 285000.00, 285000.00, 'assets/images/products/product_6aa10bf0314530.03326656.webp', '2026-09-22 18:46:48'),
(30, 25, 46, NULL, 'MINO PLAID SHORTS', 'mino-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa107ffd371e0.73412850.jpg', '2026-09-22 18:54:40'),
(31, 25, 39, NULL, 'MONOSTAR DOUBLE LAYER TEE', 'monostar-double-layer-tee', 'Medium', 1, 285000.00, 285000.00, 'assets/images/products/product_6aa10bf0314530.03326656.webp', '2026-09-22 18:54:40'),
(32, 26, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-23 04:48:10'),
(33, 27, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Medium', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-09-23 10:20:45'),
(34, 28, 47, NULL, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', '2026-09-24 21:34:06'),
(35, 29, 46, NULL, 'MINO PLAID SHORTS', 'mino-plaid-shorts', 'Medium', 1, 350000.00, 350000.00, 'assets/images/products/product_6aa107ffd371e0.73412850.jpg', '2026-09-25 04:45:07'),
(36, 30, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'X-Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-27 02:00:25'),
(37, 31, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'X-Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-09-30 10:42:52'),
(38, 32, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-10-02 15:41:42'),
(39, 33, 44, NULL, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 'Medium', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa11041da9f83.02802835.webp', '2026-10-04 04:35:33'),
(40, 34, 45, NULL, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 'Large', 1, 450000.00, 450000.00, 'assets/images/products/product_6aa110d786e607.15971827.webp', '2026-10-06 01:23:02');

-- --------------------------------------------------------

--
-- Table structure for table `order_status_logs`
--

CREATE TABLE `order_status_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `status_type` varchar(50) NOT NULL,
  `status_value` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_logs`
--

INSERT INTO `order_status_logs` (`id`, `order_id`, `status_type`, `status_value`, `notes`, `created_at`) VALUES
(1, 1, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-10 08:07:33'),
(2, 1, 'payment', 'paid', 'Verified via Kasera return redirect on index.', '2026-09-10 08:07:53'),
(3, 2, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-10 08:10:18'),
(4, 3, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-10 08:12:01'),
(5, 4, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-10 13:52:45'),
(6, 5, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-11 00:45:09'),
(7, 6, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-11 11:11:35'),
(8, 7, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-13 01:13:16'),
(9, 8, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-14 23:48:53'),
(10, 9, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 09:12:16'),
(11, 10, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 09:31:11'),
(12, 11, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 12:04:33'),
(13, 12, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 20:23:50'),
(14, 13, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 20:25:35'),
(15, 14, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 20:28:13'),
(16, 15, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 20:31:54'),
(17, 16, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-15 20:36:47'),
(18, 14, 'payment', 'paid', 'Verified via Kasera return redirect on index.', '2026-09-15 20:38:03'),
(19, 17, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-16 03:08:22'),
(20, 18, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-16 07:23:59'),
(21, 18, 'payment', 'paid', 'Verified via Kasera return redirect on index.', '2026-09-16 07:25:17'),
(22, 19, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-17 02:07:18'),
(23, 20, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-17 15:14:52'),
(24, 21, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-18 14:16:04'),
(25, 22, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-20 11:22:28'),
(26, 23, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-20 15:20:06'),
(27, 24, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-22 18:46:48'),
(28, 25, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-22 18:54:40'),
(29, 26, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-23 04:48:10'),
(30, 27, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-23 10:20:45'),
(31, 28, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-24 21:34:06'),
(32, 29, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-25 04:45:07'),
(33, 30, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-27 02:00:25'),
(34, 31, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-09-30 10:42:52'),
(35, 31, 'payment', 'paid', 'Verified via Kasera return redirect on index.', '2026-09-30 10:43:55'),
(36, 32, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-10-02 15:41:42'),
(37, 33, 'payment', 'pending', 'Order created. Waiting for Kasera Pay payment.', '2026-10-04 04:35:33'),
(38, 34, 'payment', 'pending', 'Order created. Waiting for payment method selection.', '2026-10-06 01:23:02');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `price_idr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_myr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price_sgd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_start_at` datetime DEFAULT NULL,
  `discount_end_at` datetime DEFAULT NULL,
  `is_discount_active` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `composition` text DEFAULT NULL,
  `care` text DEFAULT NULL,
  `thumbnail` varchar(255) NOT NULL,
  `size_chart_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_new` tinyint(1) NOT NULL DEFAULT 0,
  `weight_gram` int(11) NOT NULL DEFAULT 1000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `price_idr`, `price_usd`, `price_myr`, `price_sgd`, `discount_percent`, `discount_start_at`, `discount_end_at`, `is_discount_active`, `description`, `composition`, `care`, `thumbnail`, `size_chart_image`, `is_active`, `sort_order`, `created_at`, `updated_at`, `is_new`, `weight_gram`) VALUES
(39, 27, 'MONOSTAR DOUBLE LAYER TEE', 'monostar-double-layer-tee', 285000.00, 18.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- 100% Cotton 16s 235GSM \r\n- Waffle on Double Layer\r\n- Screen Print Graphic\r\n- New neck Woven Tag Design\r\n- Wash Tag\r\n\r\n*Size Tolerance 1-3cm', '100% Cotton 16s 235GSM Supersoft. Made in Indonesia', 'Normal wash with like color. DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa10bf0314530.03326656.webp', 'assets/images/products/product_6aa0f8e8e9e765.15532776.jpg', 1, 8, '2026-09-09 06:12:56', '2026-10-07 15:18:30', 1, 1000),
(40, 27, 'LOCKED IN TEE', 'locked-in-tee', 220000.00, 14.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- 100% Cotton 16s 235GSM \r\n- Screen Print Graphic\r\n- New neck Woven Tag Design\r\n- Wash Tag\r\n\r\n*Size Tolerance 1-3cm', '100% Cotton 16s 235GSM Supersoft. Made in Indonesia', 'Normal wash with like color. DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa10d8516f1b4.85412172.webp', 'assets/images/products/product_6aa0fbef2e9e69.26488663.jpg', 1, 7, '2026-09-09 06:25:51', '2026-10-07 15:18:11', 1, 1000),
(42, 27, 'BRZ TEE', 'brz-tee', 220000.00, 14.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- 100% Cotton 16s 235GSM \r\n- Waffle on Double Layer\r\n- Screen Print Graphic\r\n- New neck Woven Tag Design\r\n- Wash Tag\r\n\r\n*Size Tolerance 1-3cm', '100% Cotton 16s 235GSM Supersoft. Made in Indonesia', 'Normal wash with like color. DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa10f4b014ad1.90682238.webp', 'assets/images/products/product_6aa0fce9afda43.70795345.jpg', 1, 6, '2026-09-09 06:30:01', '2026-10-07 15:18:04', 1, 1000),
(43, 27, 'SUNZ TEE', 'sunz-tee', 220000.00, 14.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- 100% Cotton 16s 235GSM \r\n- Waffle on Double Layer\r\n- Screen Print Graphic\r\n- New neck Woven Tag Design\r\n- Wash Tag\r\n\r\n*Size Tolerance 1-3cm', '100% Cotton 16s 235GSM Supersoft. Made in Indonesia', 'Normal wash with like color. DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa10fb6d22df0.30283944.webp', 'assets/images/products/product_6aa0fd9e601bc5.31593985.jpg', 1, 5, '2026-09-09 06:33:02', '2026-10-07 15:17:46', 1, 1000),
(44, 28, 'STITCHED LIGHT BLUE BAGGY JEANS', 'stitched-light-blue-baggy-jeans', 450000.00, 26.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- Light Blue Washed\r\n- Dimes signature baggy fit\r\n- 13,5oz\r\n- Leather Patch details in back pants\r\n- \"d\" logo Embroidery in front pants\r\n- Stitched Embroidery in back pants\r\n\r\n*Size Tolerance 1-3cm', '13,5oz Denim Washed', 'DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa11041da9f83.02802835.webp', 'assets/images/products/product_6ab0bce012a2a1.82575920.jpg', 1, 2, '2026-09-09 07:00:13', '2026-10-07 15:16:56', 1, 1000),
(45, 28, 'TWIST BLACK WASHED BAGGY JEANS', 'twist-black-washed-baggy-jeans', 450000.00, 26.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- Black Washed\r\n- Dimes signature baggy fit\r\n- 13,5oz\r\n- Leather Patch details in back pants\r\n- logo Embroidery in coin pocket front pants\r\n- Embroidery in back pants\r\n- 5 Rivet in front pants\r\n- 4 Rivet in back pants\r\n\r\n*Size Tolerance 1-3cm', '13,5oz Denim', 'DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa110d786e607.15971827.webp', 'assets/images/products/product_6aa1058ad44212.74736471.jpg', 1, 1, '2026-09-09 07:06:50', '2026-10-07 15:17:31', 1, 1000),
(46, 28, 'MINO PLAID SHORTS', 'mino-plaid-shorts', 350000.00, 20.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- Acrylic Cotton\r\n- Leather patch in front of pants\r\n- Woven Tag\r\n\r\n*Size Tolerance 1-3cm', 'Acrylic Cotton', 'DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa107ffd371e0.73412850.jpg', 'assets/images/products/product_6aa107ffd41119.81616385.jpg', 1, 4, '2026-09-09 07:17:19', '2026-10-07 15:17:20', 1, 1000),
(47, 28, 'SARO PLAID SHORTS', 'saro-plaid-shorts', 350000.00, 20.00, 0.00, 0.00, NULL, NULL, NULL, 0, '- Acrylic Cotton\r\n- Leather patch in front of pants\r\n- Woven Tag\r\n\r\n*Size Tolerance 1-3cm', 'Acrylic Cotton', 'DO NOT BLEACH. Tumble dry medium. Warm Iron if Needed', 'assets/images/products/product_6aa111cfc9bcf7.68225147.jpg', 'assets/images/products/product_6aa111cfca4fa1.15233890.jpg', 1, 3, '2026-09-09 07:59:11', '2026-10-07 15:16:40', 1, 1000);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `sort_order`, `created_at`) VALUES
(162, 46, 'assets/images/products/product_6aa107ffd44013.95547553.jpg', 1, '2026-09-09 07:17:19'),
(163, 46, 'assets/images/products/product_6aa1081c956975.81134314.jpg', 2, '2026-09-09 07:17:48'),
(165, 46, 'assets/images/products/product_6aa10a0e59b557.19611974.webp', 3, '2026-09-09 07:26:06'),
(166, 46, 'assets/images/products/product_6aa10a1bc8c9e2.52330120.webp', 4, '2026-09-09 07:26:19'),
(167, 46, 'assets/images/products/product_6aa10aa789c871.08366163.webp', 5, '2026-09-09 07:28:39'),
(168, 39, 'assets/images/products/product_6aa10c10598911.51714177.webp', 1, '2026-09-09 07:34:40'),
(169, 39, 'assets/images/products/product_6aa10c1cf0f8f1.77340093.webp', 2, '2026-09-09 07:34:52'),
(170, 39, 'assets/images/products/product_6aa10c2fe3f2b0.98801730.webp', 3, '2026-09-09 07:35:11'),
(171, 39, 'assets/images/products/product_6aa10c6c6aaa58.85002632.webp', 4, '2026-09-09 07:36:12'),
(172, 39, 'assets/images/products/product_6aa10c775a0103.04373953.webp', 5, '2026-09-09 07:36:23'),
(173, 40, 'assets/images/products/product_6aa10d8c667442.25379579.webp', 1, '2026-09-09 07:41:00'),
(174, 40, 'assets/images/products/product_6aa10dc6cfd152.12721965.webp', 2, '2026-09-09 07:41:58'),
(175, 40, 'assets/images/products/product_6aa10dcfce3c14.32342913.webp', 3, '2026-09-09 07:42:07'),
(176, 40, 'assets/images/products/product_6aa10dd79f2877.44065135.webp', 4, '2026-09-09 07:42:15'),
(177, 40, 'assets/images/products/product_6aa10ddf654155.68548799.webp', 5, '2026-09-09 07:42:23'),
(178, 40, 'assets/images/products/product_6aa10de5e09f28.07867081.webp', 6, '2026-09-09 07:42:29'),
(179, 42, 'assets/images/products/product_6aa10f4b019755.32076698.webp', 1, '2026-09-09 07:48:27'),
(180, 42, 'assets/images/products/product_6aa10f6d48b8c4.47841636.webp', 2, '2026-09-09 07:49:01'),
(181, 42, 'assets/images/products/product_6aa10f75150bf1.93101267.webp', 3, '2026-09-09 07:49:09'),
(182, 42, 'assets/images/products/product_6aa10f7db8f7f7.13709552.webp', 4, '2026-09-09 07:49:17'),
(183, 42, 'assets/images/products/product_6aa10f83d371e9.32382523.webp', 5, '2026-09-09 07:49:23'),
(184, 42, 'assets/images/products/product_6aa10f89ec6f86.42929795.webp', 6, '2026-09-09 07:49:29'),
(185, 43, 'assets/images/products/product_6aa10fb6d27669.08902794.webp', 1, '2026-09-09 07:50:14'),
(186, 43, 'assets/images/products/product_6aa10fdc86aa10.45237578.webp', 2, '2026-09-09 07:50:52'),
(187, 43, 'assets/images/products/product_6aa10fe4059604.69341469.webp', 3, '2026-09-09 07:51:00'),
(188, 43, 'assets/images/products/product_6aa10fec63a2c6.11910656.webp', 4, '2026-09-09 07:51:08'),
(189, 43, 'assets/images/products/product_6aa10ff369a017.43655339.webp', 5, '2026-09-09 07:51:15'),
(190, 44, 'assets/images/products/product_6aa11041dada31.34367030.webp', 1, '2026-09-09 07:52:33'),
(191, 44, 'assets/images/products/product_6aa11049014e23.42648533.webp', 2, '2026-09-09 07:52:41'),
(192, 44, 'assets/images/products/product_6aa1108c600075.83857092.webp', 3, '2026-09-09 07:53:48'),
(193, 44, 'assets/images/products/product_6aa11094d025f9.77175496.jpg', 4, '2026-09-09 07:53:56'),
(194, 44, 'assets/images/products/product_6aa1109fad7fd6.03398809.webp', 5, '2026-09-09 07:54:07'),
(195, 44, 'assets/images/products/product_6aa110a7c50240.33316731.jpg', 6, '2026-09-09 07:54:15'),
(196, 45, 'assets/images/products/product_6aa110d7873769.44204494.webp', 1, '2026-09-09 07:55:03'),
(197, 45, 'assets/images/products/product_6aa110fdc1b4b3.70254004.webp', 2, '2026-09-09 07:55:41'),
(198, 45, 'assets/images/products/product_6aa11105481be7.05212736.webp', 3, '2026-09-09 07:55:49'),
(199, 45, 'assets/images/products/product_6aa1110fbd7bb9.73014217.webp', 4, '2026-09-09 07:55:59'),
(200, 45, 'assets/images/products/product_6aa11118286287.54635510.webp', 5, '2026-09-09 07:56:08'),
(201, 45, 'assets/images/products/product_6aa1111d874c11.07674417.webp', 6, '2026-09-09 07:56:13'),
(202, 47, 'assets/images/products/product_6aa111cfca8f35.11893705.webp', 1, '2026-09-09 07:59:11'),
(203, 47, 'assets/images/products/product_6aa111d9e35d73.13457401.webp', 2, '2026-09-09 07:59:21'),
(204, 47, 'assets/images/products/product_6aa1126a1ef9b9.68599902.webp', 3, '2026-09-09 08:01:46'),
(205, 47, 'assets/images/products/product_6aa11271016a86.82703804.webp', 4, '2026-09-09 08:01:53'),
(206, 47, 'assets/images/products/product_6aa11276a3f394.41536780.webp', 5, '2026-09-09 08:01:58');

-- --------------------------------------------------------

--
-- Table structure for table `product_sizes`
--

CREATE TABLE `product_sizes` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `size_id` int(10) UNSIGNED NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_sizes`
--

INSERT INTO `product_sizes` (`id`, `product_id`, `size_id`, `stock`, `is_active`, `created_at`, `updated_at`) VALUES
(500, 47, 3, 5, 1, '2026-10-07 15:16:40', '2026-10-07 15:16:40'),
(501, 47, 4, 4, 1, '2026-10-07 15:16:40', '2026-10-07 15:16:40'),
(502, 47, 5, 2, 1, '2026-10-07 15:16:40', '2026-10-07 15:16:40'),
(503, 44, 2, 0, 1, '2026-10-07 15:16:56', '2026-10-07 15:16:56'),
(504, 44, 3, 20, 1, '2026-10-07 15:16:56', '2026-10-07 15:16:56'),
(505, 44, 4, 19, 1, '2026-10-07 15:16:56', '2026-10-07 15:16:56'),
(506, 44, 5, 1, 1, '2026-10-07 15:16:56', '2026-10-07 15:16:56'),
(507, 46, 3, 1, 1, '2026-10-07 15:17:20', '2026-10-07 15:17:20'),
(508, 46, 4, 5, 1, '2026-10-07 15:17:20', '2026-10-07 15:17:20'),
(509, 46, 5, 0, 1, '2026-10-07 15:17:20', '2026-10-07 15:17:20'),
(510, 45, 2, 5, 1, '2026-10-07 15:17:31', '2026-10-07 15:17:31'),
(511, 45, 3, 17, 1, '2026-10-07 15:17:31', '2026-10-07 15:17:31'),
(512, 45, 4, 19, 1, '2026-10-07 15:17:31', '2026-10-07 15:17:31'),
(513, 45, 5, 8, 1, '2026-10-07 15:17:31', '2026-10-07 15:17:31'),
(514, 43, 3, 7, 1, '2026-10-07 15:17:46', '2026-10-07 15:17:46'),
(515, 43, 4, 7, 1, '2026-10-07 15:17:46', '2026-10-07 15:17:46'),
(516, 43, 5, 2, 1, '2026-10-07 15:17:46', '2026-10-07 15:17:46'),
(517, 42, 3, 8, 1, '2026-10-07 15:18:04', '2026-10-07 15:18:04'),
(518, 42, 4, 8, 1, '2026-10-07 15:18:04', '2026-10-07 15:18:04'),
(519, 42, 5, 2, 1, '2026-10-07 15:18:04', '2026-10-07 15:18:04'),
(520, 40, 3, 6, 1, '2026-10-07 15:18:11', '2026-10-07 15:18:11'),
(521, 40, 4, 7, 1, '2026-10-07 15:18:11', '2026-10-07 15:18:11'),
(522, 40, 5, 2, 1, '2026-10-07 15:18:11', '2026-10-07 15:18:11'),
(523, 39, 3, 6, 1, '2026-10-07 15:18:30', '2026-10-07 15:18:30'),
(524, 39, 4, 6, 1, '2026-10-07 15:18:30', '2026-10-07 15:18:30'),
(525, 39, 5, 2, 1, '2026-10-07 15:18:30', '2026-10-07 15:18:30');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `brand_name` varchar(100) NOT NULL DEFAULT 'BRAND',
  `meta_title` varchar(255) NOT NULL DEFAULT 'Lookbook',
  `meta_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `brand_name`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, 'BRAND', 'Lookbook', 'Lookbook collection', '2026-06-27 02:15:19', '2026-06-27 02:15:19');

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`id`, `name`, `sort_order`, `created_at`) VALUES
(1, 'X-Small', 1, '2026-04-02 08:27:52'),
(2, 'Small', 2, '2026-04-02 08:27:52'),
(3, 'Medium', 3, '2026-04-02 08:27:52'),
(4, 'Large', 4, '2026-04-02 08:27:52'),
(5, 'X-Large', 5, '2026-04-02 08:27:52'),
(6, 'XX-Large', 6, '2026-04-02 08:27:52'),
(7, 'One Size', 99, '2026-05-04 12:23:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(15, 'aqasha', 'aqasharazif@gmail.com', '$2y$10$knZNJ7DzMR4bMaRnDm2J0uCcv6ZhIOGEscd4f2gYKXBfWMoxu6jRK', 'customer', 1, '2026-09-16 07:28:32', '2026-09-16 07:28:32'),
(16, 'Devv', 'dewagantenk230@gmail.com', '$2y$10$xKSssVcVfG8nZAyrxTXyMehhmh6h9/j8Hac0YSJMxHuNvLoBzs/fC', 'customer', 1, '2026-09-16 12:06:38', '2026-09-16 12:06:38'),
(17, 'Axel', 'axello1980@icloud.com', '$2y$10$A.cjJmaSN76e.VhSEsepYu4yCEMnRrrndKssJ1YiD54qyEpRsBfgO', 'customer', 1, '2026-09-20 03:52:42', '2026-09-20 03:52:42'),
(18, 'yen', 'rayyanzufayrieee@gmail.com', '$2y$10$UWr3IR4.VuDPKKv7T6e6Q.H5.gHIYHT4wCCS16woCyQuwz1o7XsZK', 'customer', 1, '2026-09-23 21:10:16', '2026-09-23 21:10:16'),
(19, 'Khalid Al-fateh Mohamad Faizal', 'khalid_alfateh_mohamad_faizal@students.edu.sg', '$2y$10$MdPkVh1amvzxo/0nz/D24.677oMjhuKj4B9JntTLkxdPo2sKaiFWO', 'customer', 1, '2026-10-03 06:14:02', '2026-10-03 06:14:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blog_gallery`
--
ALTER TABLE `blog_gallery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_id` (`blog_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_cart_user` (`user_id`,`status`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_product_size_currency` (`cart_id`,`product_size_id`,`currency`),
  ADD KEY `fk_cart_items_product` (`product_id`),
  ADD KEY `fk_cart_items_product_size` (`product_size_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_collections_slug` (`slug`),
  ADD KEY `idx_collections_active` (`is_active`);

--
-- Indexes for table `collection_images`
--
ALTER TABLE `collection_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_images_collection` (`collection_id`),
  ADD KEY `idx_images_sort` (`sort_order`);

--
-- Indexes for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kasera_webhook_events`
--
ALTER TABLE `kasera_webhook_events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `lookbook_photos`
--
ALTER TABLE `lookbook_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_year_active` (`year`,`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `lookbook_year_titles`
--
ALTER TABLE `lookbook_year_titles`
  ADD PRIMARY KEY (`year`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `fk_orders_user` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`),
  ADD KEY `fk_order_items_product_size` (`product_size_id`);

--
-- Indexes for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_status_logs_order` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_images_product_id` (`product_id`),
  ADD KEY `idx_product_images_sort_order` (`sort_order`);

--
-- Indexes for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_size` (`product_id`,`size_id`),
  ADD KEY `fk_product_sizes_size` (`size_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `blog_gallery`
--
ALTER TABLE `blog_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `collection_images`
--
ALTER TABLE `collection_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `lookbook_photos`
--
ALTER TABLE `lookbook_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=207;

--
-- AUTO_INCREMENT for table `product_sizes`
--
ALTER TABLE `product_sizes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=526;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blog_gallery`
--
ALTER TABLE `blog_gallery`
  ADD CONSTRAINT `blog_gallery_ibfk_1` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_items_product_size` FOREIGN KEY (`product_size_id`) REFERENCES `product_sizes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `collection_images`
--
ALTER TABLE `collection_images`
  ADD CONSTRAINT `collection_images_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product_size` FOREIGN KEY (`product_size_id`) REFERENCES `product_sizes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  ADD CONSTRAINT `fk_order_status_logs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD CONSTRAINT `fk_product_sizes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_product_sizes_size` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
