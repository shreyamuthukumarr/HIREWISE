import pdfplumber
import sys


def detect_columns(words, page_width):
    """
    Detect whether the page is approximately
    single-column or two-column.
    """

    if not words:
        return "single"

    # Divide the page horizontally into small zones.
    zone_width = 40

    zones = set()

    for word in words:

        x_center = (word["x0"] + word["x1"]) / 2

        zone = int(x_center / zone_width)

        zones.add(zone)

    # Look for a significant gap between text regions.
    sorted_zones = sorted(zones)

    gaps = []

    for i in range(len(sorted_zones) - 1):

        gap = sorted_zones[i + 1] - sorted_zones[i]

        gaps.append(
            (
                gap,
                sorted_zones[i],
                sorted_zones[i + 1]
            )
        )

    if not gaps:
        return "single"

    # Largest gap between occupied regions
    largest_gap, left_zone, right_zone = max(
        gaps,
        key=lambda item: item[0]
    )

    # A gap of 4+ zones means there is probably
    # a column separation.
    if largest_gap >= 4:

        left_count = sum(
            1
            for word in words
            if int(
                ((word["x0"] + word["x1"]) / 2)
                / zone_width
            ) <= left_zone
        )

        right_count = sum(
            1
            for word in words
            if int(
                ((word["x0"] + word["x1"]) / 2)
                / zone_width
            ) >= right_zone
        )

        # Make sure both sides actually contain
        # a reasonable amount of text.
        if left_count >= 10 and right_count >= 10:
            return "two"

    return "single"


def extract_single_column(words):
    """
    Sort words from top to bottom and
    left to right.
    """

    words.sort(
        key=lambda word: (
            round(word["top"], 1),
            word["x0"]
        )
    )

    return words


def extract_two_columns(words, page_width):
    """
    Split words into left and right columns
    and read each column independently.
    """

    middle_x = page_width / 2

    left_column = []
    right_column = []

    for word in words:

        x_center = (word["x0"] + word["x1"]) / 2

        if x_center < middle_x:
            left_column.append(word)
        else:
            right_column.append(word)

    left_column = extract_single_column(left_column)
    right_column = extract_single_column(right_column)

    return left_column, right_column


def words_to_text(words):
    """
    Convert ordered words into readable text.
    """

    lines = []

    current_line = []
    previous_top = None

    for word in words:

        current_top = word["top"]

        # If vertical position changes enough,
        # start a new line.
        if (
            previous_top is not None
            and abs(current_top - previous_top) > 5
        ):

            if current_line:
                lines.append(
                    " ".join(current_line)
                )

            current_line = []

        current_line.append(word["text"])

        previous_top = current_top

    if current_line:
        lines.append(
            " ".join(current_line)
        )

    return "\n".join(lines)


# ------------------------------------------------
# MAIN
# ------------------------------------------------

pdf_path = sys.argv[1]


with pdfplumber.open(pdf_path) as pdf:

    for page_number, page in enumerate(
        pdf.pages,
        start=1
    ):

        print(
            f"\n========== PAGE {page_number} ==========\n"
        )

        words = page.extract_words()

        if not words:

            print("No text extracted.")

            continue

        layout = detect_columns(
            words,
            page.width
        )

        print(
            f"Detected layout: {layout.upper()}\n"
        )

        if layout == "single":

            ordered_words = extract_single_column(
                words
            )

            text = words_to_text(
                ordered_words
            )

            print(text)

        else:

            left_column, right_column = (
                extract_two_columns(
                    words,
                    page.width
                )
            )

            print("---------- LEFT COLUMN ----------\n")

            print(
                words_to_text(left_column)
            )

            print(
                "\n---------- RIGHT COLUMN ----------\n"
            )

            print(
                words_to_text(right_column)
            )